<?php

namespace App\Services;

use App\Models\SyncState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CrmSyncService
{
    private CrmClient $crm;

    public function __construct(CrmClient $crm)
    {
        $this->crm = $crm;
    }

    /**
     * Run sync for a given resource configuration.
     *
     * @param array $config [
     *   'resource'    => string,       // e.g. 'payments'
     *   'endpoint'    => string,       // CRM API endpoint
     *   'model'       => string,       // Eloquent model class
     *   'primaryKey'  => string,       // DB column name for primary key (e.g. 'ClientsID')
     *   'apiPrimaryKey' => string,     // API response field name for primary key (e.g. 'clientsID'), defaults to primaryKey
     *   'pageSize'    => int,          // items per page (default 1000)
     *   'pageSizeParam' => string,     // API parameter name for page size (default 'pageSize', use 'limit' for Clients)
     *   'extraParams' => array,        // extra API params
     *   'fieldMap'    => callable,      // fn($record): array — returns fields for updateOrCreate
     *   'responseKey' => string|null,  // key in response that contains items (default 'body', null = root)
     * ]
     */
    public function sync(array $config): array
    {
        $resource   = $config['resource'];
        $endpoint   = $config['endpoint'];
        $modelClass = $config['model'];
        $primaryKey = $config['primaryKey'];
        $pageSize   = $config['pageSize'] ?? 1000;
        $extraParams = $config['extraParams'] ?? [];
        $fieldMap   = $config['fieldMap'];
        $responseKey = $config['responseKey'] ?? 'body';
        $maxTime    = $config['maxExecutionTime'] ?? 180; // 3 minutes default
        $lockTime   = 300; // 5 minute lock

        $logPrefix = "[SYNC:{$resource}]";

        Log::info("{$logPrefix} Starting sync");

        $lock = Cache::lock("sync:{$resource}", $lockTime);

        // if (!$lock->get()) {
        //     Log::warning("{$logPrefix} Already running, skipping.");
        //     return ['status' => 'skipped', 'reason' => 'locked'];
        // }

        $startTime = microtime(true);
        $totalProcessed = 0;

        try {
            $state = SyncState::firstOrCreate(
                ['resource' => $resource],
                ['last_sync_at' => null, 'is_full_synced' => false, 'last_synced_id' => 0]
            );

            if ($state->is_full_synced) {
                // === INCREMENTAL MODE ===
                $result = $this->syncIncremental($state, $config, $startTime, $maxTime, $logPrefix);
            } else {
                // === FULL SYNC MODE ===
                $result = $this->syncFull($state, $config, $startTime, $maxTime, $logPrefix);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("{$logPrefix} ERROR: " . $e->getMessage(), ['exception' => $e]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        } finally {
            $lock->forceRelease();
            $elapsed = round(microtime(true) - $startTime, 2);
            Log::info("{$logPrefix} Finished in {$elapsed}s");
        }
    }

    /**
     * Full sync: fetch all records page by page (no date filter), tracking progress.
     * Full sync: fetch all records ordered by ID, using lastSyncedId as the resume checkpoint.
     * Sends lastSyncedId to CRM so it returns only records with ID > lastSyncedId.
     * No page/offset — efficient even on large tables.
     */
    private function syncFull(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix): array
    {
        $endpoint    = $config['endpoint'];
        $modelClass  = $config['model'];
        $primaryKey  = $config['primaryKey'];
        $pageSize    = $config['pageSize'] ?? 1000;
        $extraParams = $config['extraParams'] ?? [];
        $fieldMap    = $config['fieldMap'];
        $responseKey = $config['responseKey'] ?? 'body';

        $whenUpdatedKey = $config['whenUpdatedKey'] ?? 'whenUpdated';
        $pageSizeParam  = $config['pageSizeParam'] ?? 'pageSize';

        if (!$state->full_sync_started_at) {
            $state->full_sync_started_at = now();
            $state->save();
        }

        // Resume from last saved ID (0 = start from beginning)
        $lastId = (int)($state->last_synced_id ?? 0);
        $totalProcessed = 0;
        $pageMaxDate = null;

        Log::info("{$logPrefix} Full sync mode (lastSyncedId), resuming from ID {$lastId}");

        do {
            $params = array_merge([
                'lastSyncedId'  => $lastId,
                $pageSizeParam  => $pageSize,
                'page'          => 1,
            ], $extraParams);

            $resp = $this->crm->post($endpoint, $params);

            if ($resp->failed()) {
                Log::error("{$logPrefix} API request failed. Status: " . $resp->status());
                break;
            }

            $body  = $resp->json();
            $items = $this->extractItems($body, $responseKey);
            $itemCount = count($items);

            Log::info("{$logPrefix} Fetched {$itemCount} items after ID {$lastId}");

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)($r[$apiPrimaryKey] ?? 0);
                if (!$id) continue;

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    if ($id > $lastId) $lastId = $id;
                    continue;
                }

                $fields = $this->sanitizeRecord($fieldMap($r));
                $model  = $modelClass::updateOrCreate(
                    [$primaryKey => $id],
                    $fields
                );

                if (isset($config['afterSave'])) {
                    ($config['afterSave'])($r, $model);
                }

                if ($id > $lastId) {
                    $lastId = $id;
                }

                $whenUpdated = $this->validateDate($r[$whenUpdatedKey] ?? '', null);
                if ($whenUpdated && (!$pageMaxDate || $whenUpdated > $pageMaxDate)) {
                    $pageMaxDate = $whenUpdated;
                }

                $totalProcessed++;
            }

            // Save progress after each batch
            $state->last_synced_id = $lastId;
            if ($pageMaxDate) {
                $state->last_sync_at = Carbon::parse($pageMaxDate);
            }
            $state->save();

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                Log::info("{$logPrefix} Time limit ({$maxTime}s) reached after {$totalProcessed} records, last ID {$lastId}. Will continue next run.");
                return ['status' => 'partial', 'processed' => $totalProcessed, 'last_id' => $lastId];
            }

        } while ($itemCount >= $pageSize);

        // Full sync complete
        $state->is_full_synced = true;
        $state->full_sync_completed_at = now();
        $state->cursor = null;
        $state->save();
        Log::info("{$logPrefix} Full sync completed! Total processed: {$totalProcessed}, last ID: {$lastId}");

        return ['status' => 'full_sync_complete', 'processed' => $totalProcessed, 'last_id' => $lastId];
    }

    /**
     * Incremental sync: fetch records updated since last_sync_at.
     */
    private function syncIncremental(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix): array
    {
        $endpoint   = $config['endpoint'];
        $modelClass = $config['model'];
        $primaryKey = $config['primaryKey'];
        $pageSize   = $config['pageSize'] ?? 1000;
        $extraParams = $config['extraParams'] ?? [];
        $fieldMap   = $config['fieldMap'];
        $responseKey = $config['responseKey'] ?? 'body';

        $whenUpdatedKey = $config['whenUpdatedKey'] ?? 'whenUpdated';
        $orderParam     = $config['orderParam'] ?? 'WhenUpdated ASC';

        $since = $state->last_sync_at
            ? $state->last_sync_at->subSecond()->format('Y-m-d H:i:s')
            : null;

        Log::info("{$logPrefix} Incremental mode, fetching updates since {$since}");

        $page = 1;
        $totalProcessed = 0;
        $pageMaxDate = null;

        $pageSizeParam = $config['pageSizeParam'] ?? 'pageSize';

        do {
            $params = array_merge([
                'updatedSince'  => $since,
                $pageSizeParam  => $pageSize,
                'page'          => $page,
                'order'         => $orderParam,
            ], $extraParams);

            $resp = $this->crm->post($endpoint, $params);

            if ($resp->failed()) {
                Log::error("{$logPrefix} API request failed. Status: " . $resp->status());
                break;
            }

            $body = $resp->json();
            $items = $this->extractItems($body, $responseKey);
            $itemCount = count($items);

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)($r[$apiPrimaryKey] ?? 0);
                if (!$id) continue;

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    continue;
                }

                $fields = $this->sanitizeRecord($fieldMap($r));
                $model = $modelClass::updateOrCreate(
                    [$primaryKey => $id],
                    $fields
                );

                if (isset($config['afterSave'])) {
                    ($config['afterSave'])($r, $model);
                }

                $whenUpdated = $this->validateDate($r[$whenUpdatedKey] ?? '', null);
                if ($whenUpdated && (!$pageMaxDate || $whenUpdated > $pageMaxDate)) {
                    $pageMaxDate = $whenUpdated;
                }

                $totalProcessed++;
            }

            if ($pageMaxDate) {
                $state->last_sync_at = Carbon::parse($pageMaxDate);
                $state->save();
            }

            $page++;

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                Log::info("{$logPrefix} Time limit ({$maxTime}s) reached. Will continue next run.");
                return ['status' => 'partial', 'processed' => $totalProcessed];
            }

        } while ($itemCount >= $pageSize);

        Log::info("{$logPrefix} Incremental sync done. Processed: {$totalProcessed}");
        return ['status' => 'incremental_complete', 'processed' => $totalProcessed];
    }

    /**
     * Extract items from the API response based on the response key.
     */
    private function extractItems($body, ?string $responseKey): array
    {
        if (!is_array($body)) return [];

        if ($responseKey === null) {
            return $body;
        }

        // Handle nested body.body (some endpoints wrap twice)
        if (isset($body[$responseKey]) && is_array($body[$responseKey])) {
            $inner = $body[$responseKey];
            // Check if there's another nested 'body'
            if (isset($inner['body']) && is_array($inner['body'])) {
                return $inner['body'];
            }
            return $inner;
        }

        return $body;
    }

    /**
     * Validate date string, return default if invalid.
     */
    public function validateDate($date, $default = null)
    {
        if (empty($date)) return $default;
        if (str_starts_with($date, '0000') || str_starts_with($date, '-')) return $default;
        return $date;
    }

    /**
     * Replace invalid MySQL date values ('0000-00-00', '0000-00-00 00:00:00')
     * with null so MySQL strict mode does not reject the insert/update.
     * Applied automatically to every record before upsert.
     */
    private function sanitizeRecord(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if (is_string($value) && (
                $value === '0000-00-00' ||
                $value === '0000-00-00 00:00:00' ||
                str_starts_with($value, '0000-') ||
                ($value === '' && $this->isDateColumn($key))
            )) {
                $fields[$key] = null;
            }
        }
        return $fields;
    }

    /**
     * Heuristic check: does the column name suggest a date/datetime field?
     */
    private function isDateColumn(string $key): bool
    {
        $lower = strtolower($key);
        return str_contains($lower, 'date') ||
               str_starts_with($lower, 'when') ||
               str_ends_with($lower, '_at');
    }
}