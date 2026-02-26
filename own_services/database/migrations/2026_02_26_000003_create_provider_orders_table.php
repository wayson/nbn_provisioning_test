<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();           // providerOrderId
            $table->string('idempotency_key')->unique(); // caller's orderId
            $table->string('status')->default('PENDING');
            $table->string('failure_reason')->nullable();
            $table->unsignedTinyInteger('poll_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_orders');
    }
};
