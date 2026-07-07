<?php

namespace App\Services;

class CrmSyncRegistry
{
    public function all(): array
    {
        return config('crm_sync.resources', []);
    }

    public function get(string $resource): ?array
    {
        $resources = $this->all();
        $key = strtolower($resource);

        if (isset($resources[$key])) {
            return $this->normalize($key, $resources[$key]);
        }

        foreach ($resources as $name => $config) {
            $job = $config['job'] ?? '';
            if (strtolower(class_basename($job)) === $key) {
                return $this->normalize($name, $config);
            }
        }

        return null;
    }

    public function normalize(string $resource, array $config): array
    {
        return [
            'resource' => $resource,
            'endpoint' => $config['endpoint'],
            'model' => $config['model'],
            'primaryKey' => $config['primary_key'],
            'apiPrimaryKey' => $config['api_primary_key'] ?? $config['primary_key'],
            'pageSize' => $config['page_size'] ?? config('crm_sync.default_page_size', 1000),
            'responseKey' => $config['response_key'] ?? 'body',
            'whenUpdatedKey' => $config['timestamp_field'] ?? 'whenupdated',
            'incrementalFields' => $config['incremental_fields'] ?? ['whenupdated'],
            'bufferSeconds' => $config['buffer_seconds'] ?? config('crm_sync.default_buffer_seconds', 5),
            'dateColumns' => $config['date_columns'] ?? [],
            'nullableColumns' => $config['nullable_columns'] ?? [],
            'requiredColumns' => $config['required_columns'] ?? [],
            'targetTable' => $config['target_table'],
            'sourceTable' => $config['source_table'] ?? null,
            'fieldMapping' => $config['field_mapping'] ?? 'raw',
            'extraParams' => $config['extra_params'] ?? [],
            'maxExecutionTime' => $config['max_execution_time'] ?? null,
            'lockSeconds' => $config['lock_seconds'] ?? null,
            'progressLogEvery' => $config['progress_log_every'] ?? null,
        ];
    }
}
