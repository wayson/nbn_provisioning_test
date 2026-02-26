<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProviderController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{orderId}', [OrderController::class, 'show']);

Route::post('/provider/submit', [ProviderController::class, 'submit']);
Route::get('/provider/status/{providerOrderId}', [ProviderController::class, 'status']);

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response('OK');
    } catch (\Throwable $e) {
        return response('DB Connection Failed', 503);
    }
});
