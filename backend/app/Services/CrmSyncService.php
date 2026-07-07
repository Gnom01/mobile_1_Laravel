<?php

namespace App\Services;

use App\Models\SyncState;
use App\Models\SyncRunLog;
use App\Models\SyncRecordFailure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CrmSyncService
{
    /**
     * After this many failed attempts a single record is treated as poison:
     * the resume cursor is allowed to advance past it (so the whole resource
     * is not stuck forever) while the dead-letter row is kept for manual review.
     */
    private const MAX_RECORD_ATTEMPTS = 5;

    private CrmClient $crm;

    /** Cache zbioru kolumn per tabela (lowercase) — 1 zapytanie na tabelę na run. */
    private array $tableColumnsCache = [];

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
        $lockTime   = (int)($config['lockSeconds'] ?? max(900, $maxTime + 900));
        $progressLogEvery = (int)($config['progressLogEvery'] ?? 100);

        $logPrefix = "[SYNC:{$resource}]";

        $startTime = microtime(true);
        $runLog = null;
        $state = null;
        $lock = null;
        $lockAcquired = false;

        Log::info("{$logPrefix} Starting sync");

        try {
            $schemaErrors = $this->validateConfig($config);
            if (!empty($schemaErrors)) {
                Log::error("{$logPrefix} Config validation failed", ['errors' => $schemaErrors]);
                return ['status' => 'validation_failed', 'message' => implode('; ', $schemaErrors), 'failed' => count($schemaErrors)];
            }

            $lockStore = config('crm_sync.lock_store', 'database');
            $lock = Cache::store($lockStore)->lock("sync:{$resource}", $lockTime);

            if (!$lock->get()) {
                Log::warning("{$logPrefix} Already running, skipping.");
                return ['status' => 'skipped', 'reason' => 'locked'];
            }

            $lockAcquired = true;

            $state = SyncState::firstOrCreate(
                ['resource' => $resource],
                [
                    'last_sync_at' => null,
                    'last_attempt_at' => null,
                    'is_full_synced' => false,
                    'last_synced_id' => 0,
                ]
            );

            $this->markStaleRunLogs($resource, $lockTime, $logPrefix);

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
                $result = $this->syncIncremental($state, $config, $startTime, $maxTime, $logPrefix, $runLog, $progressLogEvery);
            } else {
                // === FULL SYNC MODE ===
                $result = $this->syncFull($state, $config, $startTime, $maxTime, $logPrefix, $runLog, $progressLogEvery);
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
            if ($lockAcquired && $lock) {
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
    private function syncFull(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix, ?SyncRunLog $runLog = null, int $progressLogEvery = 100): array
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

        // Two distinct positions:
        //  - $scanId       : how far we have READ from CRM (drives pagination).
        //  - $checkpointId : how far we can SAFELY resume from — the highest
        //                    contiguous prefix of records that actually saved.
        // We never persist $checkpointId past a record that failed to save, so a
        // failed record is re-fetched and retried on the next run instead of
        // being silently skipped forever.
        $scanId = (int)($state->last_synced_id ?? 0);
        $checkpointId = $scanId;
        $checkpointDate = $state->last_sync_at; // Carbon|null, only ever moves forward
        $blocked = false; // becomes true after the first unresolved failure this run
        $totalFetched = 0;
        $totalProcessed = 0;
        $totalFailed = 0;

        $openFailures = $this->loadOpenFailureIds($config);

        Log::info("{$logPrefix} Full sync mode (lastSyncedId), resuming from ID {$scanId}");

        do {
            $params = array_merge([
                'lastSyncedId'  => $scanId,
                $pageSizeParam  => $pageSize,
                'page'          => 1,
            ], $extraParams);

            $resp = $this->crm->post($endpoint, $params);

            if ($resp->failed()) {
                $message = "API request failed. Status: " . $resp->status();
                Log::error("{$logPrefix} {$message}");
                return ['status' => 'api_error', 'message' => $message, 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed + 1, 'last_id' => $checkpointId];
            }

            $body  = $resp->json();
            $items = $this->extractItems($body, $responseKey);
            $itemCount = count($items);
            $totalFetched += $itemCount;

            Log::info("{$logPrefix} Fetched {$itemCount} items after ID {$scanId}");

            $pagePosition = 0;
            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)$this->recordValue($r, [$apiPrimaryKey, $primaryKey], 0);
                if (!$id) continue;
                $pagePosition++;

                if ($this->shouldLogProgress($pagePosition, $itemCount, $progressLogEvery)) {
                    $elapsed = round(microtime(true) - $startTime, 2);
                    Log::info("{$logPrefix} Processing full-sync record {$pagePosition}/{$itemCount}, ID {$id}, elapsed {$elapsed}s");
                }

                if ($id > $scanId) {
                    $scanId = $id; // advance read position so pagination keeps moving forward
                }

                $whenUpdated = $this->validateDate($this->recordValue($r, $whenUpdatedKey, ''), null);

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    // An intentional skip counts as "handled" — safe to advance the checkpoint.
                    if (!$blocked) {
                        $checkpointId = max($checkpointId, $id);
                        $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                    }
                    continue;
                }

                try {
                    $this->upsertRecord($modelClass, $primaryKey, $id, $r, $config, $fieldMap);
                    $totalProcessed++;

                    if (isset($openFailures[(string)$id])) {
                        $this->markFailureResolved($config, $id);
                        unset($openFailures[(string)$id]);
                    }

                    if (!$blocked) {
                        $checkpointId = max($checkpointId, $id);
                        $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                    }
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $openFailures[(string)$id] = true;
                    $attempts = $this->recordFailure($runLog, $config, $id, $e->getMessage(), $r);
                    Log::error("{$logPrefix} Record {$id} failed (attempt {$attempts}): " . $e->getMessage(), [
                        'record_id' => $id,
                        'exception' => $e,
                    ]);

                    if ($attempts >= self::MAX_RECORD_ATTEMPTS) {
                        // Poison record — stop blocking the whole resource on it.
                        Log::critical("{$logPrefix} Record {$id} permanently failing after {$attempts} attempts; advancing cursor past it. Dead-letter row kept for manual review.");
                        if (!$blocked) {
                            $checkpointId = max($checkpointId, $id);
                            $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                        }
                    } else {
                        // Hold the checkpoint at the last good record so this one
                        // (and everything after it) is re-fetched next run.
                        $blocked = true;
                    }
                }
            }

            // Persist only the SAFE checkpoint after each batch.
            $state->last_synced_id = $checkpointId;
            if ($checkpointDate) {
                $state->last_sync_at = $checkpointDate;
            }
            $state->save();

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $message = "Time limit ({$maxTime}s) reached after {$totalProcessed} records, checkpoint ID {$checkpointId}. Will continue next run.";
                Log::info("{$logPrefix} {$message}", ['elapsed_seconds' => $elapsed]);
                return ['status' => 'partial', 'message' => $message, 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed, 'last_id' => $checkpointId];
            }

        } while ($itemCount >= $pageSize);

        if ($blocked) {
            // We scanned the whole table but at least one record still has an
            // unresolved hole — do NOT flip to incremental yet, or that record
            // could be skipped. Resume full sync from the checkpoint next run.
            Log::warning("{$logPrefix} Full scan finished with unresolved failures; staying in full mode (checkpoint ID {$checkpointId}).");
            return ['status' => 'full_sync_blocked', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed, 'last_id' => $checkpointId];
        }

        // Full sync complete — every record up to here saved cleanly.
        $state->is_full_synced = true;
        $state->full_sync_completed_at = now();
        $state->cursor = null;
        $state->save();
        Log::info("{$logPrefix} Full sync completed! Total processed: {$totalProcessed}, failed: {$totalFailed}, checkpoint ID: {$checkpointId}");

        return ['status' => 'full_sync_complete', 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed, 'last_id' => $checkpointId];
    }

    /**
     * Incremental sync: fetch records updated since last_sync_at.
     */
    private function syncIncremental(SyncState $state, array $config, float $startTime, int $maxTime, string $logPrefix, ?SyncRunLog $runLog = null, int $progressLogEvery = 100): array
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

        // Records arrive ordered by WhenUpdated ASC. $checkpointDate is the
        // highest date of the contiguous prefix of records that saved cleanly;
        // we never advance last_sync_at past a record that failed, so it is
        // re-queried (updatedSince) and retried next run instead of being lost.
        $checkpointDate = $state->last_sync_at; // Carbon|null, only moves forward
        $blocked = false;

        $openFailures = $this->loadOpenFailureIds($config);

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
                $message = "API request failed. Status: " . $resp->status();
                Log::error("{$logPrefix} {$message}");
                return ['status' => 'api_error', 'message' => $message, 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed + 1];
            }

            $body = $resp->json();
            $items = $this->extractItems($body, $responseKey);
            $itemCount = count($items);
            $totalFetched += $itemCount;

            $pagePosition = 0;
            foreach ($items as $r) {
                if (!is_array($r)) continue;

                $apiPrimaryKey = $config['apiPrimaryKey'] ?? $primaryKey;
                $id = (int)$this->recordValue($r, [$apiPrimaryKey, $primaryKey], 0);
                if (!$id) continue;
                $pagePosition++;

                if ($this->shouldLogProgress($pagePosition, $itemCount, $progressLogEvery)) {
                    $elapsed = round(microtime(true) - $startTime, 2);
                    Log::info("{$logPrefix} Processing incremental record {$pagePosition}/{$itemCount}, ID {$id}, page {$page}, elapsed {$elapsed}s");
                }

                $whenUpdated = $this->validateDate($this->recordValue($r, $whenUpdatedKey, ''), null);

                if (isset($config['skipIf']) && ($config['skipIf'])($r)) {
                    Log::debug("{$logPrefix} Skipping record ID {$id} (skipIf matched)");
                    if (!$blocked) {
                        $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                    }
                    continue;
                }

                try {
                    $this->upsertRecord($modelClass, $primaryKey, $id, $r, $config, $fieldMap);
                    $totalProcessed++;

                    if (isset($openFailures[(string)$id])) {
                        $this->markFailureResolved($config, $id);
                        unset($openFailures[(string)$id]);
                    }

                    if (!$blocked) {
                        $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                    }
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $openFailures[(string)$id] = true;
                    $attempts = $this->recordFailure($runLog, $config, $id, $e->getMessage(), $r);
                    Log::error("{$logPrefix} Record {$id} failed (attempt {$attempts}): " . $e->getMessage(), [
                        'record_id' => $id,
                        'exception' => $e,
                    ]);

                    if ($attempts >= self::MAX_RECORD_ATTEMPTS) {
                        Log::critical("{$logPrefix} Record {$id} permanently failing after {$attempts} attempts; advancing cursor past it. Dead-letter row kept for manual review.");
                        if (!$blocked) {
                            $checkpointDate = $this->maxDate($checkpointDate, $whenUpdated);
                        }
                    } else {
                        $blocked = true;
                    }
                }
            }

            // Persist the safe checkpoint after each page so progress survives
            // a crash or the time-limit cutoff below.
            if ($checkpointDate) {
                $state->last_sync_at = $checkpointDate;
                $state->save();
            }

            $page++;

            // Check time limit
            if ((microtime(true) - $startTime) > $maxTime) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $message = "Time limit ({$maxTime}s) reached. Will continue next run.";
                Log::info("{$logPrefix} {$message}", ['elapsed_seconds' => $elapsed]);
                return ['status' => 'partial', 'message' => $message, 'fetched' => $totalFetched, 'processed' => $totalProcessed, 'failed' => $totalFailed];
            }

        } while ($itemCount >= $pageSize);

        Log::info("{$logPrefix} Incremental sync done. Processed: {$totalProcessed}, failed: {$totalFailed}" . ($blocked ? ' (with held checkpoint — failures will retry next run)' : ''));
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

    private function markStaleRunLogs(string $resource, int $lockTime, string $logPrefix): void
    {
        if (!Schema::hasTable('sync_run_logs')) {
            return;
        }

        try {
            $staleCutoff = now()->subSeconds($lockTime);
            $count = SyncRunLog::query()
                ->where('resource', $resource)
                ->where('status', 'running')
                ->whereNull('finished_at')
                ->where('started_at', '<', $staleCutoff)
                ->update([
                    'status' => 'abandoned',
                    'finished_at' => now(),
                    'error_message' => 'Run log was still running after lock expiry; marked abandoned by next sync start.',
                ]);

            if ($count > 0) {
                Log::warning("{$logPrefix} Marked {$count} stale run log(s) as abandoned.");
            }
        } catch (\Throwable $e) {
            Log::warning("{$logPrefix} Could not mark stale run logs: " . $e->getMessage());
        }
    }

    private function shouldLogProgress(int $position, int $total, int $every): bool
    {
        if ($every <= 0) {
            return false;
        }

        return $position === 1 || $position === $total || $position % $every === 0;
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

    /**
     * Upsert a single CRM record into the local table, atomically together with
     * any afterSave side effects, so a half-applied record never persists.
     */
    private function upsertRecord(string $modelClass, string $primaryKey, int $id, array $r, array $config, callable $fieldMap): void
    {
        DB::transaction(function () use ($modelClass, $primaryKey, $id, $r, $config, $fieldMap) {
            $fields = $this->sanitizeRecord($fieldMap($this->normalizeIncomingRecord($r, $config)));
            $this->assertRequiredFields($fields, $config);
            $fields = $this->withoutPrimaryKeyAliases($fields, $config);
            // Odfiltruj klucze nieistniejące w tabeli docelowej — chroni joby
            // raw-passthrough (SELECT alias.*) przed „Unknown column" → poison,
            // gdy CRM doda kolumnę nieobecną w bazie mobilnej.
            $fields = $this->filterToTableColumns($modelClass, $fields);

            $model = $modelClass::updateOrCreate(
                [$primaryKey => $id],
                $fields
            );

            if (isset($config['afterSave'])) {
                ($config['afterSave'])($r, $model);
            }
        });
    }

    /**
     * Zostawia tylko te klucze $fields, które odpowiadają istniejącym kolumnom
     * tabeli docelowej (porównanie case-insensitive). Klucze spoza schematu są
     * pomijane — zamiast wywalać insert „Unknown column" (poison) przy dryfcie
     * schematu CRM. Jeśli schematu nie da się odczytać, zwraca pola bez zmian.
     */
    private function filterToTableColumns(string $modelClass, array $fields): array
    {
        try {
            $table = (new $modelClass())->getTable();
        } catch (\Throwable $e) {
            return $fields;
        }

        if (!array_key_exists($table, $this->tableColumnsCache)) {
            try {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                $set = [];
                foreach ($cols as $c) {
                    $set[strtolower($c)] = true;
                }
                $this->tableColumnsCache[$table] = $set;
            } catch (\Throwable $e) {
                $this->tableColumnsCache[$table] = []; // nieznany schemat → nie filtruj
            }
        }

        $set = $this->tableColumnsCache[$table];
        if (empty($set)) {
            return $fields;
        }

        $out = [];
        foreach ($fields as $k => $v) {
            if (isset($set[strtolower((string) $k)])) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Load the set of record IDs that currently have an OPEN (unresolved)
     * dead-letter entry for this resource. One query per run (dead-letters are
     * rare) instead of an UPDATE per successful record. Keyed by string id.
     *
     * @return array<string,bool>
     */
    private function loadOpenFailureIds(array $config): array
    {
        try {
            return SyncRecordFailure::where('resource', $config['resource'] ?? '')
                ->whereNull('resolved_at')
                ->pluck('record_id')
                ->filter(fn ($v) => $v !== null)
                ->flip()
                ->map(fn () => true)
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[SYNC] Could not load open failures: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Record (or update) the dead-letter entry for a record that failed to
     * upsert. Keeps a single OPEN row per (resource, record_id), incrementing
     * its attempt counter, and returns the total attempts so the caller can
     * decide whether to give up on a poison record.
     */
    private function recordFailure(?SyncRunLog $runLog, array $config, $id, string $error, array $payload): int
    {
        $resource = $config['resource'] ?? '';
        $recordId = $id !== null ? (string)$id : null;

        try {
            $existing = SyncRecordFailure::where('resource', $resource)
                ->where('record_id', $recordId)
                ->whereNull('resolved_at')
                ->first();

            if ($existing) {
                $existing->attempts = (int)$existing->attempts + 1;
                $existing->error_message = $error;
                $existing->payload = $payload;
                if ($runLog) {
                    $existing->sync_run_log_id = $runLog->id;
                }
                $existing->save();

                return (int)$existing->attempts;
            }

            SyncRecordFailure::create([
                'sync_run_log_id' => $runLog ? $runLog->id : null,
                'resource' => $resource,
                'record_id' => $recordId,
                'error_message' => $error,
                'payload' => $payload,
                'attempts' => 1,
            ]);

            return 1;
        } catch (\Throwable $e) {
            Log::warning('[SYNC] Failed to persist record failure: ' . $e->getMessage());
            // If we cannot even track the failure, report a single attempt so we
            // do NOT prematurely give up and skip the record.
            return 1;
        }
    }

    /**
     * Mark any open dead-letter entry for this record as resolved — it just
     * synced successfully on retry.
     */
    private function markFailureResolved(array $config, $id): void
    {
        try {
            SyncRecordFailure::where('resource', $config['resource'] ?? '')
                ->where('record_id', $id !== null ? (string)$id : null)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('[SYNC] Failed to resolve record failure: ' . $e->getMessage());
        }
    }

    /**
     * Return the later of an existing Carbon checkpoint and a new date string,
     * never moving the checkpoint backwards. $newDate may be null/invalid.
     */
    private function maxDate(?Carbon $current, $newDate): ?Carbon
    {
        if (empty($newDate)) {
            return $current;
        }

        try {
            $candidate = Carbon::parse($newDate);
        } catch (\Throwable $e) {
            return $current;
        }

        if (!$current || $candidate->greaterThan($current)) {
            return $candidate;
        }

        return $current;
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
