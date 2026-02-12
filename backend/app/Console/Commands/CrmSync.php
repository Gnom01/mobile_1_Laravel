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
        \App\Jobs\PullClientsJob::dispatch();
        \App\Jobs\PullUsersJob::dispatch();
        \App\Jobs\PullPaymentsJob::dispatch();
        \App\Jobs\PullUsersRelationsJob::dispatch();
        \App\Jobs\PushOutboxJob::dispatch();

        $this->info('Sync jobs dispatched');
        return self::SUCCESS;
    }
}
