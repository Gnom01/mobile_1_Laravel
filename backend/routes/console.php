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

$crmSyncJobs = [
    'PullClientsJob',
    'PullUsersJob',
    'PullPaymentsJob',
    'PullPaymentsRealJob',
    'PullPaymentsItemsJob',
    'PullLocalizationsJob',
    'PullContractsJob',
    'PullUsersRelationsJob',
    'PullDictionariesJob',
    'PullProductsJob',
    'PullCoursesJob',
    'PullEmployeesJob',
    'PullPriceListsTemplatesPositionsJob',
    'PullPriceListsTemplatesJob',
    'PullPriceListsTemplatesPositionsDimensionsJob',
    'PullProductsDimensionsJob',
    'PullCoursesHeadingsDimensionsJob',
    'PullSeasonsJob',
    'PullXSchedulesJob',
    'PullDaysJob',
    'PullDaysOffJob',
    'PullProductsPaymentInstallmentsJob',
    'PullCoursesHeadingsJob',
    'PullSchedulesEventsSettlementsJob',
    'PullUsersSchedulesJob',
    'PullUsersTicketsJob',
    'PullUserWorkshopsGroupsJob',
    'PullUsersProductsJob',
    'PullWorkshopsYgmJob',
    'PullWorkshopsEuropeanJob',
    'PullCampsJob',
    'PullDayCampsJob',
    'PullTicketsJob',
];

foreach ($crmSyncJobs as $jobName) {
    $logName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', str_replace('Job', '', str_replace('Pull', '', $jobName))));

    Schedule::command("crm:sync {$jobName}")
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->appendOutputTo(storage_path("logs/crm-sync-{$logName}.log"))
        ->before(function () use ($jobName) {
            Log::info("[SCHEDULER] crm:sync {$jobName} is about to start");
        })
        ->after(function () use ($jobName) {
            Log::info("[SCHEDULER] crm:sync {$jobName} has finished");
        })
        ->onFailure(function () use ($jobName) {
            Log::error("[SCHEDULER] crm:sync {$jobName} failed");
        });
}

Schedule::call(function () {
    PushNotification::query()
        ->where('status', PushNotification::STATUS_SCHEDULED)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->pluck('id')
        ->each(fn ($id) => SendPushNotificationJob::dispatch((int) $id));
})->everyMinute()->name('push-notifications-dispatch')->withoutOverlapping();
