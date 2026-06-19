<?php

use App\Http\Controllers\Api\CoachPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coach/plans', [CoachPlanController::class, 'store']);
    Route::put('/coach/plans/{id}', [CoachPlanController::class, 'update']);
    Route::delete('/coach/plans/{id}', [CoachPlanController::class, 'destroy']);
});
