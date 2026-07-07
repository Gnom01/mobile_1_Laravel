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
use App\Jobs\PullWorkshopsYgmJob;
use App\Jobs\PullWorkshopsEuropeanJob;
use App\Jobs\PullCampsJob;
use App\Jobs\PullDayCampsJob;
use App\Jobs\PullTicketsJob;
use App\Models\SyncState;
use App\Services\CrmSyncRegistry;
use App\Services\CrmSyncService;
use Throwable;

class CrmSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync
        {resource? : Optional job/resource name, for example coursesheadings or PullCoursesHeadingsJob}
        {--full : Reset selected sync state and run full sync}
        {--dry-run : Fetch and validate a sample without writing to the database}
        {--sample=10 : Number of CRM records to inspect during dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Main CRM synchronization command';

    /**
     * Execute the console command.
     */
    public function handle(CrmSyncRegistry $registry, CrmSyncService $syncService)
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
            // Offer sections: workshops, camps, day camps, tickets
            'PullWorkshopsYgmJob'                            => PullWorkshopsYgmJob::class,
            'PullWorkshopsEuropeanJob'                       => PullWorkshopsEuropeanJob::class,
            'PullCampsJob'                                   => PullCampsJob::class,
            'PullDayCampsJob'                                => PullDayCampsJob::class,
            'PullTicketsJob'                                 => PullTicketsJob::class,
        ];

        $requestedResource = strtolower((string) $this->argument('resource'));

        if ($requestedResource !== '') {
            $jobs = array_filter(
                $jobs,
                fn ($class, $name) => strtolower($name) === $requestedResource
                    || strtolower(class_basename($class)) === $requestedResource
                    || $this->resourceNameForJob((string) $name) === $requestedResource,
                ARRAY_FILTER_USE_BOTH
            );

            if (empty($jobs)) {
                $this->error("Unknown CRM sync resource: {$this->argument('resource')}");
                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            if ($requestedResource === '') {
                $this->error('Dry-run requires a resource name, for example: php artisan crm:sync coursesheadings --dry-run');
                return self::FAILURE;
            }

            $descriptor = $registry->get($requestedResource);
            if (!$descriptor) {
                $this->error("No sync descriptor found for {$requestedResource}. Add it to config/crm_sync.php first.");
                return self::FAILURE;
            }

            $result = $syncService->dryRun($descriptor, (int)$this->option('sample'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return ($result['status'] ?? '') === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('full')) {
            foreach (array_keys($jobs) as $name) {
                $resource = $this->resourceNameForJob($name);
                SyncState::query()->updateOrCreate(
                    ['resource' => $resource],
                    [
                        'last_sync_at' => null,
                        'cursor' => null,
                        'last_synced_id' => 0,
                        'is_full_synced' => false,
                        'full_sync_started_at' => null,
                        'full_sync_completed_at' => null,
                    ]
                );
                Log::warning("[CRM:SYNC] Forced full sync state reset for resource: {$resource}");
            }
        }

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

    private function resourceNameForJob(string $jobName): string
    {
        $map = [
            'PullPaymentsJob' => 'payments',
            'PullPaymentsRealJob' => 'payments_real',
            'PullCoursesHeadingsDimensionsJob' => 'coursesheadingsdimensions',
            'PullCoursesHeadingsJob' => 'coursesheadings',
            'PullPriceListsTemplatesPositionsJob' => 'priceliststemplatespositions',
            'PullPriceListsTemplatesJob' => 'priceliststemplates',
            'PullPriceListsTemplatesPositionsDimensionsJob' => 'priceliststemplatespositionsdimensions',
            'PullProductsDimensionsJob' => 'productsdimensions',
            'PullProductsPaymentInstallmentsJob' => 'productspaymentinstallments',
            'PullSchedulesEventsSettlementsJob' => 'scheduleseventssettlements',
            'PullUsersSchedulesJob' => 'usersschedules',
            'PullUsersTicketsJob' => 'userstickets',
            'PullUserWorkshopsGroupsJob' => 'userworkshopsgroups',
            'PullUsersProductsJob' => 'usersproducts',
            'PullWorkshopsYgmJob' => 'workshops_ygm',
            'PullWorkshopsEuropeanJob' => 'workshops_european',
            'PullDayCampsJob' => 'day_camps',
        ];

        return $map[$jobName] ?? strtolower(str_replace(['Pull', 'Job'], '', $jobName));
    }
}
