<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\ThirdPartyOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitOrderJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries;
    public int $timeout = 30;

    public function __construct(
        public readonly string $orderId,
    ) {
        $this->tries = config('services.order_provider.max_attempts', 3);
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping($this->orderId)];
    }

    /** Randomised exponential backoff (jitter) to avoid thundering herd */
    public function backoff(): array
    {
        return array_map(
            fn (int $attempt) => random_int(
                (int) (10 * (2 ** $attempt) * 0.5),
                (int) (10 * (2 ** $attempt) * 1.5),
            ),
            range(0, $this->tries - 1)
        );
    }

    public function handle(ThirdPartyOrderService $service): void
    {
        $order   = Order::findOrFail($this->orderId);
        $attempt = $this->attempts();

        if (in_array($order->status, [OrderStatus::COMPLETED, OrderStatus::FAILED])) {
            return;
        }

        try {
            if ($order->provider_order_id === null) {
                $submitResponse = $service->submitOrder($order->id);
                $this->transitionStatus(
                    $order,
                    OrderStatus::SUBMITTED, 
                    from: $order->status, 
                    attempt: $attempt, 
                    providerResult: $submitResponse['data']);
                $order->update(['provider_order_id' => $submitResponse['data']['providerOrderId']]);
            }

            $this->pollStatusesUntilCompletedOrFailed($order, $service, $attempt);
        } catch (Throwable $e) {
            $reason = $e instanceof RequestException
                ? "HTTP {$e->response->status()}: {$e->response->body()}"
                : $e->getMessage();

            // Record the error for this attempt without changing order status —
            // the job will retry. failed() will mark it FAILED after the last attempt.
            $order->statusHistories()->create([
                'from_status'    => $order->fresh()->status,
                'status'         => OrderStatus::FAILED,
                'failure_reason' => "[Attempt {$attempt}] {$reason}",
                'attempt'        => $attempt,
                'created_at'     => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $order = Order::find($this->orderId);

        if (! $order || in_array($order->status, [OrderStatus::COMPLETED, OrderStatus::FAILED])) {
            return;
        }

        $reason = $e instanceof RequestException
            ? "HTTP {$e->response->status()}: {$e->response->body()}"
            : $e->getMessage();

        $this->transitionStatus(
            $order,
            OrderStatus::FAILED,
            from: $order->status,
            attempt: $this->attempts(),
            failureReason: $reason,
        );
    }

    private function pollStatusesUntilCompletedOrFailed(Order $order, ThirdPartyOrderService $service, int $attempt): void
    {
        $maxPolls    = 5;
        $pollDelayMs = 2000;

        for ($i = 0; $i < $maxPolls; $i++) {
            $poll = $service->getStatus($order->provider_order_id);
            $data = $poll['data'];

            if ($data['status'] === OrderStatus::COMPLETED->value) {
                $this->transitionStatus(
                    $order,
                    OrderStatus::COMPLETED,
                    from: $order->fresh()->status,
                    attempt: $attempt,
                    providerResult: $data,
                    providerDurationMs: $poll['duration_ms'],
                );
                return;
            }

            if ($data['status'] === OrderStatus::FAILED->value) {
                throw new \RuntimeException($data['failure_reason'] ?? 'Provider reported failure');
            }

            Log::info('Log order status');

            $this->transitionStatus(
                    $order,
                    $data['status'],
                    from: $order->fresh()->status,
                    attempt: $attempt,
                    providerResult: $data,
                    providerDurationMs: $poll['duration_ms'],
                );

            usleep($pollDelayMs * 1000);
        }

        throw new \RuntimeException("Provider order {$order->provider_order_id} did not resolve after {$maxPolls} polls");
    }

    private function transitionStatus(
        Order $order,
        OrderStatus|string $status,
        ?OrderStatus $from = null,
        int $attempt = 1,
        ?array $providerResult = null,
        ?int $providerDurationMs = null,
        ?string $failureReason = null,
    ): void {
        if (is_string($status)) {
            $status = OrderStatus::from($status);
        }
        
        $order->update(['status' => $status]);

        $order->statusHistories()->create([
            'from_status'          => $from ?? $order->getOriginal('status'),
            'status'               => $status,
            'failure_reason'       => $failureReason,
            'provider_result'      => $providerResult,
            'provider_duration_ms' => $providerDurationMs,
            'attempt'              => $attempt,
            'created_at'           => now(),
        ]);
    }
}
