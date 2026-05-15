<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PasswordResetController;

// ───────────────────────────────────────────────
// Public routes (no authentication required)
// ───────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/sms/send', [OtpController::class, 'requestOtp']);
Route::post('/sms/verify', [OtpController::class, 'verifyOtp']);
Route::post('/sms/login/send', [OtpController::class, 'requestOtp']);
Route::post('/sms/login/verify', [OtpController::class, 'loginListAccounts']);
Route::post('/sms/login/select-account', [OtpController::class, 'selectAccountLogin']);
Route::post('/password/reset', [PasswordResetController::class, 'requestReset']);
Route::post('/password/reset/verify', [PasswordResetController::class, 'verifyResetCode']);
Route::post('/password/reset/confirm', [PasswordResetController::class, 'confirmReset']);

Route::post('/auth/otp/request', [OtpController::class, 'requestOtp']); // deprecated
Route::post('/auth/otp/verify', [OtpController::class, 'verifyOtp']); // deprecated

// ───────────────────────────────────────────────
// Protected auth routes
// ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/password/set', [OtpController::class, 'setPassword']);
    Route::match(['get', 'post', 'put'], '/user/profile', [AuthController::class, 'profile']);
    Route::match(['get', 'post', 'put'], '/user/consents', [AuthController::class, 'consents']);
});