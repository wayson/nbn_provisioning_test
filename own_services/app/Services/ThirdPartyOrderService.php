<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ThirdPartyOrderService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.order_provider.base_url');
        $this->timeout = config('services.order_provider.timeout');
    }

    /**
     * @return array{data: array, duration_ms: int}
     * @throws ConnectionException|RequestException
     */
    public function submitOrder(string $orderId): array
    {
        $start    = hrtime(true);
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/provider/submit", [
                'idempotency_key' => $orderId,
            ]);
        $duration = (int) ((hrtime(true) - $start) / 1_000_000);

        $response->throw();

        return ['data' => $response->json(), 'duration_ms' => $duration];
    }

    /**
     * @return array{data: array, duration_ms: int}
     * @throws ConnectionException|RequestException
     */
    public function getStatus(string $providerOrderId): array
    {
        $start    = hrtime(true);
        $response = Http::timeout($this->timeout)
            ->get("{$this->baseUrl}/provider/status/{$providerOrderId}");
        $duration = (int) ((hrtime(true) - $start) / 1_000_000);

        $response->throw();

        return ['data' => $response->json(), 'duration_ms' => $duration];
    }
}
