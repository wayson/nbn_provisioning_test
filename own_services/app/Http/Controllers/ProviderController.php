<?php

namespace App\Http\Controllers;

use App\Models\ProviderOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $request->validate(['idempotency_key' => 'required|string']);

        $this->maybeFail('PROVIDER_SIM_SUBMIT_ERROR', 'PROVIDER_SIM_SUBMIT_TIMEOUT');

        $idempotencyKey = $request->input('idempotency_key');

        $order = ProviderOrder::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            ['status' => 'PENDING', 'poll_count' => 0],
        );

        return response()->json([
            'providerOrderId' => $order->id,
            'status'          => $order->status,
        ], 202);
    }

    public function status(string $providerOrderId): JsonResponse
    {
        $order = ProviderOrder::findOrFail($providerOrderId);

        $this->maybeFail('PROVIDER_SIM_STATUS_ERROR', 'PROVIDER_SIM_STATUS_TIMEOUT');

        if ($order->status === 'PENDING') {
            $order->increment('poll_count');
            $order->refresh();

            $pollsToComplete = (int) env('PROVIDER_SIM_POLLS_TO_COMPLETE', 3);

            if ($order->poll_count >= $pollsToComplete) {
                $this->resolveOrder($order);
            }
        }

        $payload = ['providerOrderId' => $order->id, 'status' => $order->status];

        if ($order->status === 'FAILED') {
            $payload['failure_reason'] = $order->failure_reason;
        }

        return response()->json($payload);
    }

    private function maybeFail(string $errorEnv, string $timeoutEnv): void
    {
        if (env($timeoutEnv, false)) {
            sleep(12);
        }

        if (env($errorEnv, false)) {
            abort(500, 'Provider internal error');
        }
    }

    private function resolveOrder(ProviderOrder $order): void
    {
        if (env('PROVIDER_SIM_FORCE_FAILURE', false)) {
            $order->update([
                'status'         => 'FAILED',
                'failure_reason' => env('PROVIDER_SIM_FAILURE_REASON', 'Simulated failure'),
            ]);
            return;
        }

        $order->update(['status' => 'COMPLETED']);
    }
}
