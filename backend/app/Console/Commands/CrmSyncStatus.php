<?php

namespace App\Console\Commands;

use App\Models\SyncRunLog;
use App\Models\SyncState;
use Illuminate\Console\Command;

class CrmSyncStatus extends Command
{
    protected $signature = 'crm:sync:status {--delay= : Minutes after which a resource is marked delayed}';
    protected $description = 'Show CRM synchronization status per resource';

    public function handle(): int
    {
        $delay = (int)($this->option('delay') ?: config('crm_sync.delay_warning_minutes', 15));
        $latestRuns = SyncRunLog::query()
            ->latest('id')
            ->get()
            ->unique('resource')
            ->keyBy('resource');

        $rows = SyncState::query()
            ->orderBy('resource')
            ->get()
            ->map(function (SyncState $state) use ($latestRuns, $delay) {
                $run = $latestRuns->get($state->resource);
                $finished = $run ? $run->finished_at : null;
                $duration = ($run && $run->started_at && $run->finished_at)
                    ? $run->finished_at->diffInSeconds($run->started_at) . 's'
                    : null;

                return [
                    'resource' => $state->resource,
                    'last_start' => optional($run ? $run->started_at : null)->toDateTimeString(),
                    'last_end' => optional($finished)->toDateTimeString(),
                    'status' => $run->status ?? null,
                    'last_sync_at' => optional($state->last_sync_at)->toDateTimeString(),
                    'last_id' => $state->last_synced_id,
                    'fetched' => $run->fetched_count ?? null,
                    'processed' => $run->processed_count ?? null,
                    'failed' => $run->failed_count ?? null,
                    'duration' => $duration,
                    'delayed' => (!$finished || $finished->lt(now()->subMinutes($delay))) ? 'YES' : 'no',
                    'last_error' => $run->error_message ?? null,
                ];
            })
            ->all();

        $this->table([
            'resource', 'last_start', 'last_end', 'status', 'last_sync_at', 'last_id',
            'fetched', 'processed', 'failed', 'duration', 'delayed', 'last_error',
        ], $rows);

        return self::SUCCESS;
    }
}
