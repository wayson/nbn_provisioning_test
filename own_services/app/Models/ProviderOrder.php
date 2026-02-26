<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProviderOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'status',
        'failure_reason',
        'poll_count',
    ];
}
