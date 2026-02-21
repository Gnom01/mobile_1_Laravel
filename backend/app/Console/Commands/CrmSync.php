<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\PullClientsJob;
use App\Jobs\PullUsersJob;
use App\Jobs\PullPaymentsJob;
use App\Jobs\PullPaymentsRealJob;
use App\Jobs\PullPaymentsItemsJob;
use App\Jobs\PullLocalizationsJob;
use App\Jobs\PullContractsJob;
use App\Jobs\PullUsersRelationsJob;
use App\Jobs\PushOutboxJob;
use Throwable;

class CrmSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Main CRM synchronization command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('[CRM:SYNC] ===== crm:sync command started =====');
        $this->info('Starting CRM sync...');

        $jobs = [
            'PullClientsJob'        => PullClientsJob::class,
            'PullUsersJob'          => PullUsersJob::class,
            'PullPaymentsJob'       => PullPaymentsJob::class,
            'PullPaymentsRealJob'   => PullPaymentsRealJob::class,
            'PullPaymentsItemsJob'  => PullPaymentsItemsJob::class,
            'PullLocalizationsJob'  => PullLocalizationsJob::class,
            'PullContractsJob'      => PullContractsJob::class,
            'PullUsersRelationsJob' => PullUsersRelationsJob::class,
            'PushOutboxJob'         => PushOutboxJob::class,
        ];

        foreach ($jobs as $name => $jobClass) {
            Log::info("[CRM:SYNC] Starting job: {$name}");
            $this->info("Running {$name}...");
            $start = microtime(true);

            try {
                $jobClass::dispatchSync();
                $duration = round(microtime(true) - $start, 2);
                Log::info("[CRM:SYNC] Job completed: {$name} in {$duration}s");
                $this->info("{$name} completed in {$duration}s");
            } catch (Throwable $e) {
                Log::error("[CRM:SYNC] Job FAILED: {$name} - " . $e->getMessage());
                $this->error("{$name} failed: " . $e->getMessage());
                Log::error("{$name} failed: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        Log::info('[CRM:SYNC] ===== crm:sync command finished =====');
        $this->info('CRM sync finished.');
        return self::SUCCESS;
    }
}
