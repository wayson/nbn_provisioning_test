<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'status',
        'from_status',
        'failure_reason',
        'provider_result',
        'provider_duration_ms',
        'attempt',
        'created_at',
    ];

    protected $casts = [
        'status'          => OrderStatus::class,
        'from_status'     => OrderStatus::class,
        'provider_result' => 'array',
        'created_at'      => 'datetime',
    ];
}
