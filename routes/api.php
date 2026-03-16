<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Webhook\UberDirectController;
use App\Http\Controllers\Webhook\UberEatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Order API ───────────────────────────────────────────────
Route::prefix('orders')->group(function () {
    Route::get('/',              [OrderController::class, 'index']);
    Route::post('/',             [OrderController::class, 'store']);
    Route::patch('/{id}/ready',  [OrderController::class, 'markReady']);
});

// ─── Webhooks ────────────────────────────────────────────────
Route::prefix('webhook')->group(function () {
    Route::post('/uber-eats/orders',    [UberEatsController::class, 'handleOrder']);
    Route::post('/uber-direct/status',  [UberDirectController::class, 'handleStatus']);
});

// ─── Test / Mock Routes (local only) ─────────────────────────
// if (app()->isLocal()) {
//     Route::prefix('test')->group(function () {
//         Route::post('/uber-eats/simulate-order',
//             [\App\Http\Controllers\Test\FakeUberEatsDriverController::class, 'simulateOrder']
//         );
//         Route::post('/uber-direct/simulate-status',
//             [\App\Http\Controllers\Test\FakeUberEatsDriverController::class, 'simulateDeliveryStatus']
//         );
//         Route::post('/simulate-full-flow',
//             [\App\Http\Controllers\Test\FakeUberEatsDriverController::class, 'simulateFullFlow']
//         );
//     });
// }