<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        Log::info('[KERNEL] schedule() called - registering crm:sync every 5 minutes');

        $schedule->command('crm:sync')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->before(function () {
                Log::info('[KERNEL] crm:sync is about to start');
            })
            ->after(function () {
                Log::info('[KERNEL] crm:sync has finished');
            });
    }
}
