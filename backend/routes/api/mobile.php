<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CrmUserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ScheduleChangesController;
use App\Http\Controllers\Api\UsersRelationsController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Controllers\Api\CampController;
use App\Http\Controllers\Api\DayCampController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\MobilePushController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InstructorController;

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

    // Contracts
    Route::get('/contracts/{parentGuid}', [ContractController::class, 'getContracts']);
    Route::post('/checkout/schedule/start', [CheckoutController::class, 'startScheduleCheckout']);
    Route::post('/checkout/{checkoutSession}/refresh', [CheckoutController::class, 'refreshStatus']);

    // Relations
    Route::get('/users-relations/{parentGuid}', [UsersRelationsController::class, 'getRelatedUsers']);
    Route::post('/users-relations', [UsersRelationsController::class, 'store']);

    // Calendar
    Route::get('/calendar/people/{parentGuid}', [CalendarController::class, 'getPeople']);
    Route::get('/calendar/month/{parentGuid}', [CalendarController::class, 'getMonthSummary']);
    Route::get('/calendar/day/{parentGuid}', [CalendarController::class, 'getDayEvents']);

    // Schedule changes
    Route::get('/schedule-changes/missed/{parentGuid}', [ScheduleChangesController::class, 'getMissedLessons']);
    Route::get('/schedule-changes/workoffs/{parentGuid}', [ScheduleChangesController::class, 'getWorkoffLessons']);

    // Dictionaries & courses
    Route::get('/dictionaries', [DictionaryController::class, 'index']);
    Route::get('/dictionaries/courses', [DictionaryController::class, 'getCoursesDictionaries']);
    Route::get('/courses/dictionaries', [DictionaryController::class, 'getCoursesDictionaries']);
    Route::get('/dictionaries/camps',   [DictionaryController::class, 'getCampDictionaries']);
    Route::get('/camps/dictionaries',   [DictionaryController::class, 'getCampDictionaries']);

    // Courses search
    Route::post('/courses/search', [CourseController::class, 'search']);

    // PDF generation
    Route::post('/pdf/generate', [PdfController::class, 'generate']);
    Route::post('/pdf/preview',  [PdfController::class, 'preview']);

    // Pricing
    Route::get('/pricing/course/{coursesHeadingsID}', [PricingController::class, 'getPriceByCourseHeadingsID']);
    Route::post('/GetPriceByCourseHeadingsID', [PricingController::class, 'getPrice']);
    Route::get('/pricing/entry-fee', [PricingController::class, 'checkEntryFee']);

    // Orders (CRM-first)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/camps', [OrderController::class, 'store']);
    Route::post('/orders/day-camps', [OrderController::class, 'store']);
    Route::post('/orders/workshops', [OrderController::class, 'store']);
    Route::post('/orders/tickets', [OrderController::class, 'store']);


    // Instructor (grupy + komunikaty push)
    Route::get('/instructor/groups', [InstructorController::class, 'groups']);
    Route::get('/instructor/groups/{groupId}/participants', [InstructorController::class, 'participants']);
    Route::post('/instructor/messages', [InstructorController::class, 'sendMessage']);

    // Push notifications
    Route::get('/mobile/dashboard/banners', [DashboardController::class, 'banners']);
    Route::post('/mobile/device-tokens', [MobilePushController::class, 'registerDeviceToken']);
    Route::delete('/mobile/device-tokens/{token}', [MobilePushController::class, 'deleteDeviceToken']);
    Route::get('/mobile/notifications', [MobilePushController::class, 'index']);
    Route::get('/mobile/notifications/unread-count', [MobilePushController::class, 'unreadCount']);
    Route::get('/mobile/notifications/{id}', [MobilePushController::class, 'show']);
    Route::post('/mobile/notifications/{id}/read', [MobilePushController::class, 'markRead']);
    Route::post('/mobile/notifications/{id}/opened', [MobilePushController::class, 'markOpened']);
    Route::post('/mobile/notifications/read-all', [MobilePushController::class, 'readAll']);

    // ─── Offer sections ─────────────────────────────
    // Workshops Pricing & Checkout Wizards
    Route::post('/offers/workshops/calculate-pricing', [WorkshopController::class, 'calculatePricing']);
    Route::post('/orders/workshops/checkout',          [WorkshopController::class, 'checkout']);

    // Workshops YGM
    Route::get('/offers/workshops/ygm',          [WorkshopController::class, 'indexYgm']);
    Route::get('/offers/workshops/ygm/{id}',     [WorkshopController::class, 'showYgm']);
    // Workshops European
    Route::get('/offers/workshops/european',     [WorkshopController::class, 'indexEuropean']);
    Route::get('/offers/workshops/european/{id}',[WorkshopController::class, 'showEuropean']);
    // Camps
    Route::get('/offers/camps',                  [CampController::class, 'index']);
    Route::get('/offers/camps/{id}',             [CampController::class, 'show']);
    // Day camps (półkolonie)
    Route::get('/offers/day-camps',              [DayCampController::class, 'index']);
    Route::get('/offers/day-camps/{id}',         [DayCampController::class, 'show']);
    // Tickets
    Route::get('/offers/tickets',                [TicketController::class, 'index']);
    Route::get('/offers/tickets/{id}',           [TicketController::class, 'show']);
    // Pricing
    Route::get('/pricing/camp/{id}',             [CampController::class, 'pricing']);
    Route::get('/pricing/day-camp/{id}',         [DayCampController::class, 'pricing']);
    Route::get('/pricing/ticket/{id}',           [TicketController::class, 'pricing']);
});