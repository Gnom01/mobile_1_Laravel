<?php

namespace Tests\Unit;

use App\Services\CrmClient;
use App\Services\CrmSyncService;
use Tests\TestCase;

class CrmSyncStandardTest extends TestCase
{
    public function testEveryConfiguredResourceHasRequiredDescriptorFields(): void
    {
        foreach (config('crm_sync.resources') as $resource => $config) {
            foreach ([
                'source_table',
                'target_table',
                'endpoint',
                'model',
                'primary_key',
                'api_primary_key',
                'full_sync_id_field',
                'incremental_fields',
                'timestamp_field',
                'date_columns',
                'nullable_columns',
                'required_columns',
                'field_mapping',
                'page_size',
                'mode',
            ] as $field) {
                $this->assertArrayHasKey($field, $config, "{$resource} misses {$field}");
            }

            $this->assertContains('whenupdated', array_map('strtolower', $config['incremental_fields']));
            $this->assertNotEmpty($config['required_columns']);
        }
    }

    public function testNormalizerHandlesDirtyNullAndDateValues(): void
    {
        $service = new CrmSyncService($this->createMock(CrmClient::class));

        $record = $service->normalizeIncomingRecord([
            'empty' => '',
            'text' => '  abc  ',
            'null_text' => '(null)',
        ]);

        $this->assertNull($record['empty']);
        $this->assertSame('abc', $record['text']);
        $this->assertNull($record['null_text']);
        $this->assertNull($service->validateDate('0000-00-00', null));
        $this->assertNull($service->validateDate('00.00.0000', null));
    }
}
