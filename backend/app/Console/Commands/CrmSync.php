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
use App\Jobs\PullDictionariesJob;
use App\Jobs\PullCoursesJob;
use App\Jobs\PullEmployeesJob;
use App\Jobs\PullProductsJob;
use App\Jobs\PushOutboxJob;
use App\Jobs\PullPriceListsTemplatesPositionsJob;
use App\Jobs\PullPriceListsTemplatesJob;
use App\Jobs\PullPriceListsTemplatesPositionsDimensionsJob;
use App\Jobs\PullProductsDimensionsJob;
use App\Jobs\PullCoursesHeadingsDimensionsJob;
use App\Jobs\PullSeasonsJob;
use App\Jobs\PullXSchedulesJob;
use App\Jobs\PullDaysJob;
use App\Jobs\PullDaysOffJob;
use App\Jobs\PullProductsPaymentInstallmentsJob;
use App\Jobs\PullCoursesHeadingsJob;
use App\Jobs\PullSchedulesEventsSettlementsJob;
use App\Jobs\PullUsersSchedulesJob;
use App\Jobs\PullUsersTicketsJob;
use App\Jobs\PullUserWorkshopsGroupsJob;
use App\Jobs\PullUsersProductsJob;
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
            'PullDictionariesJob'   => PullDictionariesJob::class,
            'PullProductsJob'       => PullProductsJob::class,
            'PullCoursesJob'        => PullCoursesJob::class,
            'PullEmployeesJob'      => PullEmployeesJob::class,
            'PushOutboxJob'         => PushOutboxJob::class,
            // New: 10 additional CRM tables (lowercase, no field mapping)
            'PullPriceListsTemplatesPositionsJob'           => PullPriceListsTemplatesPositionsJob::class,
            'PullPriceListsTemplatesJob'                    => PullPriceListsTemplatesJob::class,
            'PullPriceListsTemplatesPositionsDimensionsJob' => PullPriceListsTemplatesPositionsDimensionsJob::class,
            'PullProductsDimensionsJob'                     => PullProductsDimensionsJob::class,
            'PullCoursesHeadingsDimensionsJob'              => PullCoursesHeadingsDimensionsJob::class,
            'PullSeasonsJob'                                => PullSeasonsJob::class,
            'PullXSchedulesJob'                             => PullXSchedulesJob::class,
            'PullDaysJob'                                   => PullDaysJob::class,
            'PullDaysOffJob'                                => PullDaysOffJob::class,
            'PullProductsPaymentInstallmentsJob'            => PullProductsPaymentInstallmentsJob::class,
            'PullCoursesHeadingsJob'                        => PullCoursesHeadingsJob::class,
            'PullSchedulesEventsSettlementsJob'             => PullSchedulesEventsSettlementsJob::class,
            'PullUsersSchedulesJob'                         => PullUsersSchedulesJob::class,
            'PullUsersTicketsJob'                           => PullUsersTicketsJob::class,
            'PullUserWorkshopsGroupsJob'                    => PullUserWorkshopsGroupsJob::class,
            'PullUsersProductsJob'                          => PullUsersProductsJob::class,
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
