<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CrmUserController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UsersRelationsController;
use App\Http\Controllers\Api\DictionaryController;

// ───────────────────────────────────────────────
// Public routes (no authentication required)
// ───────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/otp/request', [OtpController::class, 'requestOtp']);
Route::post('/auth/otp/verify', [OtpController::class, 'verifyOtp']);

// ───────────────────────────────────────────────
// Protected routes (require authentication token)
// ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/password/set', [OtpController::class, 'setPassword']);
    Route::match(['get', 'post', 'put'], '/user/profile', [AuthController::class, 'profile']);
    Route::match(['get', 'post', 'put'], '/user/consents', [AuthController::class, 'consents']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);

    // Users
    Route::put('/users/{id}', [CrmUserController::class, 'update']);
    Route::get('/payments/schedule', [PaymentController::class, 'getSchedule']);
    Route::get('/payments/history/{parentGuid}', [PaymentController::class, 'getPaymentHistory']);
    Route::get('/users-relations/{parentGuid}', [UsersRelationsController::class, 'getRelatedUsers']);
    Route::get('/dictionaries', [DictionaryController::class, 'index']);
});
