<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Jobs\SubmitOrderJob;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(): JsonResponse
    {
        $order = Order::create([
            'status'         => OrderStatus::RECEIVED,
        ]);

        SubmitOrderJob::dispatch($order->id);

        return response()->json([
            'orderId'       => $order->id,
            'status'        => $order->status->value,
        ], 201);
    }

    public function show(string $orderId): JsonResponse
    {
        $order = Order::with('statusHistories')->findOrFail($orderId);

        return response()->json([
            'orderId'       => $order->id,
            'status'        => $order->status->value,
            'history'       => $order->statusHistories->map(fn ($h) => array_filter([
                'from'               => $h->from_status?->value,
                'to'                 => $h->status->value,
                'attempt'            => $h->attempt,
                'timestamp'          => $h->created_at->toIso8601String(),
                'failure_reason'     => $h->failure_reason,
                'provider_result'    => $h->provider_result,
                'provider_duration_ms' => $h->provider_duration_ms,
            ], fn ($v) => $v !== null)),
        ]);
    }
}
