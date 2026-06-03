<?php

use App\Http\Controllers\Api\CrmPushController;
use App\Http\Controllers\Api\CrmDashboardBannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm/push')->group(function () {
    Route::post('/preview-recipients', [CrmPushController::class, 'previewRecipients']);
    Route::get('/notifications', [CrmPushController::class, 'index']);
    Route::post('/notifications', [CrmPushController::class, 'store']);
    Route::post('/notifications/{id}/send', [CrmPushController::class, 'send']);
    Route::post('/notifications/{id}/schedule', [CrmPushController::class, 'schedule']);
    Route::get('/notifications/{id}/status', [CrmPushController::class, 'status']);
    Route::post('/test', [CrmPushController::class, 'test']);
});

Route::prefix('crm/dashboard/banners')->group(function () {
    Route::get('', [CrmDashboardBannerController::class, 'index']);
    Route::post('', [CrmDashboardBannerController::class, 'store']);
    Route::patch('/reorder', [CrmDashboardBannerController::class, 'reorder']);
    Route::put('/{id}', [CrmDashboardBannerController::class, 'update']);
    Route::patch('/{id}', [CrmDashboardBannerController::class, 'update']);
    Route::delete('/{id}', [CrmDashboardBannerController::class, 'destroy']);
});
