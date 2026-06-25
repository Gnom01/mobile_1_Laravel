<?php

namespace App\Console\Commands;

use App\Services\CrmClient;
use App\Services\CrmSyncRegistry;
use App\Services\CrmSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmSyncCompare extends Command
{
    protected $signature = 'crm:sync:compare {resource?} {--all : Compare all configured resources} {--limit=20 : Missing ID sample size}';
    protected $description = 'Compare CRM sample/checkpoints with mobile tables';

    public function handle(CrmSyncRegistry $registry, CrmSyncService $syncService, CrmClient $crm): int
    {
        $resources = $this->option('all')
            ? $registry->all()
            : [$this->argument('resource') => $registry->get((string)$this->argument('resource'))];

        if (!$this->option('all') && !$this->argument('resource')) {
            $this->error('Pass a resource name or use --all.');
            return self::FAILURE;
        }

        $rows = [];
        foreach ($resources as $name => $rawConfig) {
            if (!$rawConfig) {
                continue;
            }

            $config = isset($rawConfig['primary_key']) ? $registry->normalize((string)$name, $rawConfig) : $rawConfig;
            $table = $config['targetTable'];
            $pk = $config['primaryKey'];
            $apiPk = $config['apiPrimaryKey'];
            $timestamp = $config['whenUpdatedKey'] ?? 'whenupdated';

            $mobileCount = Schema::hasTable($table) ? DB::table($table)->count() : null;
            $mobileMaxId = Schema::hasTable($table) && Schema::hasColumn($table, $pk) ? DB::table($table)->max($pk) : null;
            $mobileMaxDate = Schema::hasTable($table) && $this->hasColumn($table, $timestamp)
                ? DB::table($table)->max($this->existingColumn($table, $timestamp))
                : null;

            $resp = $crm->post($config['endpoint'], array_merge([
                'lastSyncedId' => max(0, ((int)$mobileMaxId) - (int)$this->option('limit')),
                'pageSize' => (int)$this->option('limit'),
                'page' => 1,
            ], $config['extraParams'] ?? []));

            $items = $this->extractItems($resp->json(), $config['responseKey'] ?? 'body');
            $crmIds = array_values(array_filter(array_map(
                fn ($r) => is_array($r) ? (int)$syncService->recordValue($r, [$apiPk, $pk], 0) : 0,
                $items
            )));
            $mobileIds = empty($crmIds) || !Schema::hasTable($table)
                ? []
                : DB::table($table)->whereIn($pk, $crmIds)->pluck($pk)->map(fn ($v) => (int)$v)->all();

            $rows[] = [
                'resource' => $config['resource'],
                'mobile_count' => $mobileCount,
                'mobile_max_id' => $mobileMaxId,
                'crm_sample_max_id' => empty($crmIds) ? null : max($crmIds),
                'mobile_max_date' => $mobileMaxDate,
                'missing_sample_ids' => implode(',', array_slice(array_diff($crmIds, $mobileIds), 0, 10)),
            ];
        }

        $this->table(['resource', 'mobile_count', 'mobile_max_id', 'crm_sample_max_id', 'mobile_max_date', 'missing_sample_ids'], $rows);

        return self::SUCCESS;
    }

    private function extractItems($body, ?string $responseKey): array
    {
        if (!is_array($body)) return [];
        if ($responseKey === null) return $body;
        if (isset($body[$responseKey]) && is_array($body[$responseKey])) return $body[$responseKey];
        return $body;
    }

    private function hasColumn(string $table, string $column): bool
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

    private function existingColumn(string $table, string $column): string
    {
        foreach (Schema::getColumnListing($table) as $existing) {
            if (strtolower($existing) === strtolower($column)) {
                return $existing;
            }
        }

        return $column;
    }
}
