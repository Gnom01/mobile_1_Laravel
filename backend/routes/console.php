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

Log::info('[CONSOLE.PHP] routes/console.php loaded - registering crm:sync schedule');

Schedule::command('crm:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->before(function () {
        Log::info('[SCHEDULER] crm:sync is about to start');
    })
    ->after(function () {
        Log::info('[SCHEDULER] crm:sync has finished');
    });

Schedule::call(function () {
    PushNotification::query()
        ->where('status', PushNotification::STATUS_SCHEDULED)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->pluck('id')
        ->each(fn ($id) => SendPushNotificationJob::dispatch((int) $id));
})->everyMinute()->name('push-notifications-dispatch')->withoutOverlapping();
