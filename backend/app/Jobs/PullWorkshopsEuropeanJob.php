<?php

namespace App\Jobs;

use App\Models\WorkshopEuropean;
use App\Services\CrmSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullWorkshopsEuropeanJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'      => 'workshops_european',
            'endpoint'      => '/CrmToMobileSync/getWorkshopEuropeanListData',
            'model'         => WorkshopEuropean::class,
            'primaryKey'    => 'crm_id',
            'apiPrimaryKey' => 'coursesHeadingsID',
            'pageSize'      => 500,
            'responseKey'   => 'body',
            'extraParams'   => [
                'current_LocalizationsID' => '0',
            ],

            'fieldMap' => function (array $r) use ($syncService): array {
                $int = fn ($value): ?int => $value === null ? null : (int) $value;
                $intAny = fn (array $keys): ?int => $int($this->firstValue($r, $keys));
                $string = fn ($value): ?string => $value === null ? null : (string) $value;

                return [
                    'courses_headings_id' => $int($r['coursesHeadingsID']     ?? null),
                    'parent_courses_headings_id' => $intAny([
                        'Parent_CoursesHeadingsID',
                        'parent_CoursesHeadingsID',
                        'parentCoursesHeadingsID',
                        'parent_coursesheadingsid',
                    ]),
                    'products_id'         => $int($r['productsID']            ?? null),
                    'title'               => $string($r['courseHeadingName']  ?? null),
                    'description'         => $string($r['description']        ?? null),
                    'offer_type'          => 'workshop_european',
                    'website_status_id'   => $int($r['websiteStatusesDVID']   ?? null),
                    'is_closed'           => $int($r['isClosed']              ?? null),
                    'cancelled'           => (int)($r['cancelled']            ?? 0),
                    'starts_at'           => $syncService->validateDate($r['startingDate']    ?? '', null),
                    'ends_at'             => $syncService->validateDate($r['closingDate']     ?? '', null),
                    'localization_id'     => $int($r['localizationsID']       ?? null),
                    'localization_name'   => $string($r['localizationName']   ?? null),
                    'age_range_id'        => $int($r['courseAgeRangesDVID']   ?? null),
                    'age_range_name'      => $string($r['courseAgeRanges']    ?? null),
                    'category_id'         => $int($r['mainCategoryDID']       ?? null),
                    'category_name'       => $string($r['mainCategoryName']   ?? null),
                    'level_id'            => $int($r['courseLevelDID']        ?? null),
                    'level_name'          => $string($r['courseLevel']        ?? null),
                    'style_id'            => $int($r['courseDanceStyleDID']   ?? null),
                    'style_name'          => $string($r['courseDanceStyle']   ?? null),
                    'instructors'         => $string($r['instructorsList']    ?? null),
                    'next_event_date'     => $syncService->validateDate($r['eventDate']       ?? '', null),
                    'start_time'          => $string($r['courseTimeName']     ?? null),
                    'available_places'    => $int($r['availablePlaces']       ?? null),
                    'capacity'            => $int($r['capacity']              ?? null),
                    'workshop_type'       => $string($r['workshopType']       ?? null),
                    'group_id'            => $int($r['groupID']               ?? null),
                    'workshop_level'      => $string($r['workshopLevel']      ?? null),
                    'enrollment_mode'     => $string($r['enrollmentMode']     ?? null),
                    'raw_crm_payload'     => json_encode($r),
                    'crm_updated_at'      => $syncService->validateDate($r['whenUpdated']     ?? '', null),
                ];
            },
        ]);
    }

    private function firstValue(array $record, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        $lower = [];
        foreach ($record as $key => $value) {
            $lower[strtolower((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $lookup = strtolower((string) $key);
            if (array_key_exists($lookup, $lower)) {
                return $lower[$lookup];
            }
        }

        return null;
    }
}
