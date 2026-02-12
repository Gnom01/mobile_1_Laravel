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
        $this->info('Starting CRM sync...');

        $jobs = [
            'PullClientsJob' => \App\Jobs\PullClientsJob::class,
            'PullUsersJob' => \App\Jobs\PullUsersJob::class,
            'PullPaymentsJob' => \App\Jobs\PullPaymentsJob::class,
            'PullUsersRelationsJob' => \App\Jobs\PullUsersRelationsJob::class,
            'PushOutboxJob' => \App\Jobs\PushOutboxJob::class,
        ];

        foreach ($jobs as $name => $jobClass) {
            $this->info("Running {$name}...");
            $start = microtime(true);

            try {
                $jobClass::dispatchSync();
                $duration = round(microtime(true) - $start, 2);
                $this->info("{$name} completed in {$duration}s");
            } catch (\Throwable $e) {
                $this->error("{$name} failed: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error("{$name} failed: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        $this->info('CRM sync finished.');
        return self::SUCCESS;
    }
}
