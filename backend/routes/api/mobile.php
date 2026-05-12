<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CrmUserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UsersRelationsController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\PdfController;

// ───────────────────────────────────────────────
// Mobile data query routes (require authentication)
// ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);

    // Users
    Route::post('/users/{guid}/credentials', [CrmUserController::class, 'updateCredentials']);
    Route::put('/users/{id}', [CrmUserController::class, 'update']);

    // Payments
    Route::get('/payments/schedule', [PaymentController::class, 'getSchedule']);
    Route::get('/payments/history/{parentGuid}', [PaymentController::class, 'getPaymentHistory']);
    Route::post('/checkout/schedule/start', [CheckoutController::class, 'startScheduleCheckout']);
    Route::post('/checkout/{checkoutSession}/refresh', [CheckoutController::class, 'refreshStatus']);

    // Relations
    Route::get('/users-relations/{parentGuid}', [UsersRelationsController::class, 'getRelatedUsers']);

    // Dictionaries & courses
    Route::get('/dictionaries', [DictionaryController::class, 'index']);
    Route::get('/dictionaries/courses', [DictionaryController::class, 'getCoursesDictionaries']);
    Route::get('/courses/dictionaries', [DictionaryController::class, 'getCoursesDictionaries']);

    // Courses search
    Route::post('/courses/search', [CourseController::class, 'search']);

    // PDF generation
    Route::post('/pdf/generate', [PdfController::class, 'generate']);
    Route::post('/pdf/preview',  [PdfController::class, 'preview']);
});