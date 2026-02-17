<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CrmUserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UsersRelationsController;

// ───────────────────────────────────────────────
// Public routes (no authentication required)
// ───────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ───────────────────────────────────────────────
// Protected routes (require authentication token)
// ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::match(['get', 'post', 'put'], '/user/profile', [AuthController::class, 'profile']);
    Route::match(['get', 'post', 'put'], '/user/consents', [AuthController::class, 'consents']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);

    // Users
    Route::put('/users/{id}', [CrmUserController::class, 'update']);
    Route::get('/payments/schedule', [PaymentController::class, 'getSchedule']);
    Route::get('/users-relations/{parentGuid}', [UsersRelationsController::class, 'getRelatedUsers']);
});
