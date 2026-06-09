<?php

namespace App\Services;

use App\Models\SyncState;
use App\Models\SyncRunLog;
use App\Models\SyncRecordFailure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
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

        $schemaErrors = $this->validateConfig($config);
        if (!empty($schemaErrors)) {
            Log::error("{$logPrefix} Config validation failed", ['errors' => $schemaErrors]);
            return ['status' => 'validation_failed', 'message' => implode('; ', $schemaErrors), 'failed' => count($schemaErrors)];
        }

        $lockStore = config('crm_sync.lock_store', 'file');
        $lock = Cache::store($lockStore)->lock("sync:{$resource}", $lockTime);
        $lockAcquired = false;

        // if (!$lock->get()) {
        //     Log::warning("{$logPrefix} Already running, skipping.");
        //     return ['status' => 'skipped', 'reason' => 'locked'];
        // }

        $lockAcquired = true;

        $startTime = microtime(true);
        $totalProcessed = 0;
        $runLog = null;
        $state = null;

        try {
            $state = SyncState::firstOrCreate(
                ['resource' => $resource],
                [
                    'last_sync_at' => null,
                    'last_attempt_at' => null,
                    'is_full_synced' => false,
                    'last_synced_id' => 0,
                ]
            );

            if (Schema::hasTable('sync_run_logs')) {
                $runLog = SyncRunLog::create([
                    'resource' => $resource,
                    'mode' => $state->is_full_synced ? 'incremental' : 'full',
                    'status' => 'running',
                    'started_at' => now(),
                    'last_synced_id_before' => (int)($state->last_synced_id ?? 0),
                    'last_sync_at_before' => $state->last_sync_at,
                ]);
            }

            if ($state->is_full_synced) {
                // === INCREMENTAL MODE ===
                $result = $this->syncIncremental($state, $config, $startTime, $maxTime, $logPrefix, $runLog);
            } else {
                // === FULL SYNC MODE ===
                $result = $this->syncFull($state, $config, $startTime, $maxTime, $logPrefix, $runLog);
            }

            $state->last_attempt_at = now();
            $state->save();

            $this->finishRunLog($runLog, $state, $result);

            return $result;

        } catch (\Throwable $e) {
            Log::error("{$logPrefix} ERROR: " . $e->getMessage(), ['exception' => $e]);
            if ($state) {
                $state->last_attempt_at = now();
                $state->save();
            }
            if ($runLog) {
                $runLog->update([
                    'status' => 'error',
                    'finished_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
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
    private function syncFull(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix, ?SyncRunLog $runLog = null): array
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
        $totalFetched = 0;
        $totalProcessed = 0;
        $totalFailed = 0;
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
            $totalFetched += $itemCount;

            Log::info("{$logPrefix} Fetched {$itemCount} items after ID {$lastId}");

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)$this->recordValue($r, [$apiPrimaryKey, $primaryKey], 0);
                if (!$id) continue;

                if ($id > $lastId) {
                    $lastId = $id;
                }

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    continue;
                }

                try {
                    $fields = $this->sanitizeRecord($fieldMap($this->normalizeIncomingRecord($r, $config)));
                    $this->assertRequiredFields($fields, $config);
                    $fields = $this->withoutPrimaryKeyAliases($fields, $config);
                    $model  = $modelClass::updateOrCreate(
                        [$primaryKey => $id],
                        $fields
                    );

                    if (isset($config['afterSave'])) {
                        ($config['afterSave'])($r, $model);
                    }

                    $totalProcessed++;
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $this->logRecordFailure($runLog, $config, $id, null, null, $e->getMessage(), $r);
                    Log::error("{$logPrefix} Record {$id} failed, continuing sync: " . $e->getMessage(), [
                        'record_id' => $id,
                        'record' => $r,
                        'exception' => $e,
                    ]);
                }

                $whenUpdated = $this->validateDate($this->recordValue($r, $whenUpdatedKey, ''), null);
                if ($whenUpdated && (!$pageMaxDate || $whenUpdated > $pageMaxDate)) {
                    $pageMaxDate = $whenUpdated;
                }
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
                return ['status' => 'partial', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed, 'last_id' => $lastId];
            }

        } while ($itemCount >= $pageSize);

        // Full sync complete
        $state->is_full_synced = true;
        $state->full_sync_completed_at = now();
        $state->cursor = null;
        $state->save();
        Log::info("{$logPrefix} Full sync completed! Total processed: {$totalProcessed}, failed: {$totalFailed}, last ID: {$lastId}");

        return ['status' => 'full_sync_complete', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed, 'last_id' => $lastId];
    }

    /**
     * Incremental sync: fetch records updated since last_sync_at.
     */
    private function syncIncremental(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix, ?SyncRunLog $runLog = null): array
    {
        $endpoint   = $config['endpoint'];
        $modelClass = $config['model'];
        $primaryKey = $config['primaryKey'];
        $pageSize   = $config['pageSize'] ?? 1000;
        $extraParams = $config['extraParams'] ?? [];
        $fieldMap   = $config['fieldMap'];
        $responseKey = $config['responseKey'] ?? 'body';

        $whenUpdatedKey = $config['whenUpdatedKey'] ?? 'whenUpdated';
        $incrementalFields = $config['incrementalFields'] ?? [$whenUpdatedKey];
        $orderParam     = $config['orderParam'] ?? 'WhenUpdated ASC';
        $bufferSeconds  = (int)($config['bufferSeconds'] ?? 1);

        $since = $state->last_sync_at
            ? $state->last_sync_at->copy()->subSeconds($bufferSeconds)->format('Y-m-d H:i:s')
            : null;

        Log::info("{$logPrefix} Incremental mode, fetching updates since {$since}");

        $page = 1;
        $totalFetched = 0;
        $totalProcessed = 0;
        $totalFailed = 0;
        $pageMaxDate = null;

        $pageSizeParam = $config['pageSizeParam'] ?? 'pageSize';

        do {
            $params = array_merge([
                'updatedSince'  => $since,
                'insertedSince'  => $since,
                'incrementalFields' => $incrementalFields,
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
            $totalFetched += $itemCount;

            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)$this->recordValue($r, [$apiPrimaryKey, $primaryKey], 0);
                if (!$id) continue;

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    continue;
                }

                try {
                    $fields = $this->sanitizeRecord($fieldMap($this->normalizeIncomingRecord($r, $config)));
                    $this->assertRequiredFields($fields, $config);
                    $fields = $this->withoutPrimaryKeyAliases($fields, $config);
                    $model = $modelClass::updateOrCreate(
                        [$primaryKey => $id],
                        $fields
                    );

                    if (isset($config['afterSave'])) {
                        ($config['afterSave'])($r, $model);
                    }

                    $totalProcessed++;
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $this->logRecordFailure($runLog, $config, $id, null, null, $e->getMessage(), $r);
                    Log::error("{$logPrefix} Record {$id} failed, continuing sync: " . $e->getMessage(), [
                        'record_id' => $id,
                        'record' => $r,
                        'exception' => $e,
                    ]);
                }

                $whenUpdated = $this->validateDate($this->recordValue($r, $whenUpdatedKey, ''), null);
                if ($whenUpdated && (!$pageMaxDate || $whenUpdated > $pageMaxDate)) {
                    $pageMaxDate = $whenUpdated;
                }
            }

            $page++;

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                Log::info("{$logPrefix} Time limit ({$maxTime}s) reached. Will continue next run.");
                return ['status' => 'partial', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed];
            }

        } while ($itemCount >= $pageSize);

        if ($pageMaxDate) {
            $state->last_sync_at = Carbon::parse($pageMaxDate);
            $state->save();
        }

        Log::info("{$logPrefix} Incremental sync done. Processed: {$totalProcessed}, failed: {$totalFailed}");
        return ['status' => 'incremental_complete', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed];
    }

    private function finishRunLog(?SyncRunLog $runLog, SyncState $state, array $result): void
    {
        if (!$runLog) {
            return;
        }

        $runLog->update([
            'status' => (string)($result['status'] ?? 'finished'),
            'finished_at' => now(),
            'fetched_count' => (int)($result['fetched'] ?? 0),
            'processed_count' => (int)($result['processed'] ?? 0),
            'failed_count' => (int)($result['failed'] ?? 0),
            'last_synced_id_after' => (int)($state->last_synced_id ?? 0),
            'last_sync_at_after' => $state->last_sync_at,
            'error_message' => (string)($result['message'] ?? ''),
        ]);
    }

    public function dryRun(array $config, int $sampleSize = 10): array
    {
        $schemaErrors = $this->validateConfig($config);

        $pageSizeParam = $config['pageSizeParam'] ?? 'pageSize';
        $params = array_merge([
            'lastSyncedId' => 0,
            $pageSizeParam => $sampleSize,
            'page' => 1,
        ], $config['extraParams'] ?? []);

        $resp = $this->crm->post($config['endpoint'], $params);
        $items = $this->extractItems($resp->json(), $config['responseKey'] ?? 'body');

        $records = [];
        $warnings = [];
        $fieldMap = $config['fieldMap'] ?? fn(array $r): array => $r;

        foreach (array_slice($items, 0, $sampleSize) as $record) {
            if (!is_array($record)) {
                continue;
            }

            $normalized = $this->normalizeIncomingRecord($record, $config);
            $mapped = $this->sanitizeRecord($fieldMap($normalized));
            $id = $this->recordValue($record, [$config['apiPrimaryKey'] ?? $config['primaryKey'], $config['primaryKey']], null);

            foreach (($config['requiredColumns'] ?? []) as $column) {
                if (!array_key_exists($column, $mapped) && !array_key_exists(strtolower($column), $mapped)) {
                    $warnings[] = "Record {$id}: missing required field {$column}";
                    continue;
                }

                $value = $this->recordValue($mapped, [$column, strtolower($column)], null);
                if ($value === null || $value === '') {
                    $warnings[] = "Record {$id}: empty required field {$column}";
                }
            }

            foreach (($config['dateColumns'] ?? []) as $column) {
                $original = $this->recordValue($record, [$column, strtolower($column)], null);
                if (is_string($original) && $original !== '' && $this->validateDate($original, null) === null) {
                    $warnings[] = "Record {$id}: dirty date {$column}={$original}";
                }
            }

            $records[] = [
                'id' => $id,
                'source' => array_slice($record, 0, 8, true),
                'mapped' => array_slice($mapped, 0, 8, true),
            ];
        }

        return [
            'status' => empty($schemaErrors) ? 'ok' : 'validation_failed',
            'schema_errors' => $schemaErrors,
            'fetched_sample_count' => count($items),
            'sample' => $records,
            'warnings' => $warnings,
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        $modelClass = $config['model'] ?? null;
        $targetTable = $config['targetTable'] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            $errors[] = 'Target model does not exist.';
        }

        if (!$targetTable && $modelClass && class_exists($modelClass)) {
            $targetTable = (new $modelClass())->getTable();
        }

        if (!$targetTable || !Schema::hasTable($targetTable)) {
            $errors[] = "Target table does not exist: {$targetTable}";
            return $errors;
        }

        $primaryKey = $config['primaryKey'] ?? null;
        if (!$primaryKey || !$this->tableHasColumn($targetTable, $primaryKey)) {
            $errors[] = "Primary key column missing in {$targetTable}: {$primaryKey}";
        }

        foreach (($config['requiredColumns'] ?? []) as $column) {
            if (!$this->tableHasColumn($targetTable, $column)) {
                $errors[] = "Required column missing in {$targetTable}: {$column}";
            }
        }

        return $errors;
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
        $value = trim((string)$date);
        if ($value === '' || $value === '(null)' || strtolower($value) === 'null') return $default;
        if (str_starts_with($value, '0000') || str_starts_with($value, '-')) return $default;
        if (preg_match('/^0{1,2}[.\-\/]0{1,2}[.\-\/]0{4}/', $value)) return $default;
        return $date;
    }

    public function recordValue(array $record, $keys, $default = null)
    {
        foreach ((array)$keys as $key) {
            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        $lowerMap = [];
        foreach ($record as $key => $value) {
            if (is_string($key)) {
                $lowerMap[strtolower($key)] = $value;
            }
        }

        foreach ((array)$keys as $key) {
            $lowerKey = strtolower((string)$key);
            if (array_key_exists($lowerKey, $lowerMap)) {
                return $lowerMap[$lowerKey];
            }
        }

        return $default;
    }

    public function normalizeIncomingRecord(array $record, array $config = []): array
    {
        $normalized = [];
        foreach ($record as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue($value)
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strtolower($value) === 'null' || $value === '(null)') {
                return null;
            }
        }

        return $value;
    }

    private function assertRequiredFields(array $fields, array $config): void
    {
        foreach (($config['requiredColumns'] ?? []) as $column) {
            $value = $this->recordValue($fields, [$column, strtolower($column)], null);
            if ($value === null || $value === '') {
                throw new \RuntimeException("Required field is empty: {$column}");
            }
        }
    }

    private function logRecordFailure(?SyncRunLog $runLog, array $config, $id, ?string $field, $originalValue, string $error, array $payload): void
    {
        try {
            SyncRecordFailure::create([
                'sync_run_log_id' => $runLog ? $runLog->id : null,
                'resource' => $config['resource'] ?? '',
                'record_id' => $id !== null ? (string)$id : null,
                'field' => $field,
                'original_value' => $originalValue !== null ? (string)$originalValue : null,
                'error_message' => $error,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SYNC] Failed to persist record failure: ' . $e->getMessage());
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (Schema::hasColumn($table, $column) || Schema::hasColumn($table, strtolower($column))) {
            return true;
        }

        $wanted = strtolower($column);
        foreach (Schema::getColumnListing($table) as $existing) {
            if (strtolower($existing) === $wanted) {
                return true;
            }
        }

        return false;
    }

    private function withoutPrimaryKeyAliases(array $fields, array $config): array
    {
        $primaryAliases = array_filter([
            $config['primaryKey'] ?? null,
            $config['apiPrimaryKey'] ?? null,
        ]);

        if (empty($primaryAliases)) {
            return $fields;
        }

        $primaryAliases = array_map(fn ($key) => strtolower((string)$key), $primaryAliases);

        foreach (array_keys($fields) as $key) {
            if (in_array(strtolower((string)$key), $primaryAliases, true)) {
                unset($fields[$key]);
            }
        }

        return $fields;
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
                preg_match('/^0{1,2}[.\-\/]0{1,2}[.\-\/]0{4}/', trim($value)) ||
                strtolower(trim($value)) === 'null' ||
                trim($value) === '(null)' ||
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