<?php

use App\Http\Controllers\Api\ClientSubscriptionController;
use App\Http\Controllers\Api\CoachPlanController;
use App\Http\Controllers\Api\CoachSubscriptionController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

// Public routes — no auth required
Route::get('/plans', [SubscriptionPlanController::class, 'index']);
Route::post('/webhooks/payment', [PaymentWebhookController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coach/plans', [CoachPlanController::class, 'store']);
    Route::put('/coach/plans/{id}', [CoachPlanController::class, 'update']);
    Route::delete('/coach/plans/{id}', [CoachPlanController::class, 'destroy']);

    Route::post('/client/subscriptions', [ClientSubscriptionController::class, 'store']);
    Route::get('/client/subscriptions', [ClientSubscriptionController::class, 'index']);
    Route::post('/client/subscriptions/{id}/cancel', [ClientSubscriptionController::class, 'cancel']);

    Route::get('/coach/subscriptions', [CoachSubscriptionController::class, 'index']);
});
