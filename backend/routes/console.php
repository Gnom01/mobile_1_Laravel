<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Log::info('[CONSOLE.PHP] routes/console.php loaded - registering CRM schedules');

Schedule::useCache('file');

Schedule::command('crm:sync PullUsersJob')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/crm-sync-users.log'))
    ->before(function () {
        Log::info('[SCHEDULER] crm:sync PullUsersJob is about to start');
    })
    ->after(function () {
        Log::info('[SCHEDULER] crm:sync PullUsersJob has finished');
    })
    ->onFailure(function () {
        Log::error('[SCHEDULER] crm:sync PullUsersJob failed');
    });

Schedule::command('crm:sync PullUsersRelationsJob')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/crm-sync-usersrelations.log'))
    ->before(function () {
        Log::info('[SCHEDULER] crm:sync PullUsersRelationsJob is about to start');
    })
    ->after(function () {
        Log::info('[SCHEDULER] crm:sync PullUsersRelationsJob has finished');
    })
    ->onFailure(function () {
        Log::error('[SCHEDULER] crm:sync PullUsersRelationsJob failed');
    });

Schedule::command('crm:sync')
    ->hourly()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/crm-sync-full.log'))
    ->before(function () {
        Log::info('[SCHEDULER] crm:sync is about to start');
    })
    ->after(function () {
        Log::info('[SCHEDULER] crm:sync has finished');
    })
    ->onFailure(function () {
        Log::error('[SCHEDULER] crm:sync failed');
    });

Schedule::call(function () {
    PushNotification::query()
        ->where('status', PushNotification::STATUS_SCHEDULED)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->pluck('id')
        ->each(fn ($id) => SendPushNotificationJob::dispatch((int) $id));
})->everyMinute()->name('push-notifications-dispatch')->withoutOverlapping();
