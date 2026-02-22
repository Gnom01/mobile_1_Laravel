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

        if (!$lock->get()) {
            Log::warning("{$logPrefix} Already running, skipping.");
            return ['status' => 'skipped', 'reason' => 'locked'];
        }

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
     * Uses $state->cursor to store the last completed page number for resume.
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

        if (!$state->full_sync_started_at) {
            $state->full_sync_started_at = now();
            $state->save();
        }

        // Resume from last completed page (stored in cursor)
        $page = max(1, (int)($state->cursor ?? 0) + 1);
        $totalProcessed = 0;
        $pageMaxDate = null;
        $lastId = (int)($state->last_synced_id ?? 0);

        Log::info("{$logPrefix} Full sync mode, resuming from page {$page}");

        $pageSizeParam = $config['pageSizeParam'] ?? 'pageSize';

        do {
            $params = array_merge([
                'updatedSince'  => null,
                $pageSizeParam  => $pageSize,
                'page'          => $page,
                'order'         => 'WhenUpdated ASC',
            ], $extraParams);

            $resp = $this->crm->post($endpoint, $params);

            if ($resp->failed()) {
                Log::error("{$logPrefix} API request failed. Status: " . $resp->status());
                break;
            }

            $body = $resp->json();
            $items = $this->extractItems($body, $responseKey);
            $itemCount = count($items);

            Log::info("{$logPrefix} Page {$page}: received {$itemCount} items");

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)($r[$apiPrimaryKey] ?? 0);
                if (!$id) continue;

                $fields = $fieldMap($r);
                $modelClass::updateOrCreate(
                    [$primaryKey => $id],
                    $fields
                );

                if ($id > $lastId) {
                    $lastId = $id;
                }

                // Track max WhenUpdated for later incremental sync
                $whenUpdated = $this->validateDate($r['whenUpdated'] ?? '', null);
                if ($whenUpdated && (!$pageMaxDate || $whenUpdated > $pageMaxDate)) {
                    $pageMaxDate = $whenUpdated;
                }

                $totalProcessed++;
            }

            // Save progress after each page (page number in cursor, max id in last_synced_id)
            $state->cursor = (string)$page;
            $state->last_synced_id = $lastId;
            if ($pageMaxDate) {
                $state->last_sync_at = Carbon::parse($pageMaxDate);
            }
            $state->save();

            $page++;

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                Log::info("{$logPrefix} Time limit ({$maxTime}s) reached after {$totalProcessed} records on page {$page}. Will continue next run.");
                return ['status' => 'partial', 'processed' => $totalProcessed, 'last_id' => $lastId, 'page' => $page - 1];
            }

        } while ($itemCount > 0);

        // Full sync complete
        if ($totalProcessed > 0 || $lastId > 0) {
            $state->is_full_synced = true;
            $state->full_sync_completed_at = now();
            $state->cursor = null; // Reset cursor after full sync
            $state->save();
            Log::info("{$logPrefix} Full sync completed! Total processed: {$totalProcessed}");
        }

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
                'order'         => 'WhenUpdated ASC',
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

                $fields = $fieldMap($r);
                $modelClass::updateOrCreate(
                    [$primaryKey => $id],
                    $fields
                );

                $whenUpdated = $this->validateDate($r['whenUpdated'] ?? '', null);
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
}
