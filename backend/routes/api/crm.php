<?php

use App\Http\Controllers\Api\CrmPushController;
use Illuminate\Support\Facades\Route;

Route::prefix('crm/push')->group(function () {
    Route::post('/preview-recipients', [CrmPushController::class, 'previewRecipients']);
    Route::post('/notifications', [CrmPushController::class, 'store']);
    Route::post('/notifications/{id}/send', [CrmPushController::class, 'send']);
    Route::post('/notifications/{id}/schedule', [CrmPushController::class, 'schedule']);
    Route::get('/notifications/{id}/status', [CrmPushController::class, 'status']);
    Route::post('/test', [CrmPushController::class, 'test']);
});
