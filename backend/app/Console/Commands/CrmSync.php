<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \Illuminate\Support\Facades\Log::info('[CRM:SYNC] ===== crm:sync command started =====');
        $this->info('Starting CRM sync...');

        $jobs = [
            'PullClientsJob' => \App\Jobs\PullClientsJob::class,
            'PullUsersJob' => \App\Jobs\PullUsersJob::class,
            'PullPaymentsJob' => \App\Jobs\PullPaymentsJob::class,
            'PullUsersRelationsJob' => \App\Jobs\PullUsersRelationsJob::class,
            'PushOutboxJob' => \App\Jobs\PushOutboxJob::class,
        ];

        foreach ($jobs as $name => $jobClass) {
            \Illuminate\Support\Facades\Log::info("[CRM:SYNC] Starting job: {$name}");
            $this->info("Running {$name}...");
            $start = microtime(true);

            try {
                $jobClass::dispatchSync();
                $duration = round(microtime(true) - $start, 2);
                \Illuminate\Support\Facades\Log::info("[CRM:SYNC] Job completed: {$name} in {$duration}s");
                $this->info("{$name} completed in {$duration}s");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("[CRM:SYNC] Job FAILED: {$name} - " . $e->getMessage());
                $this->error("{$name} failed: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error("{$name} failed: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        \Illuminate\Support\Facades\Log::info('[CRM:SYNC] ===== crm:sync command finished =====');
        $this->info('CRM sync finished.');
        return self::SUCCESS;
    }
}
