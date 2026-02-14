<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

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
