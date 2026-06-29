<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CoursePrice;
use App\Services\CrmSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullCoursesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'    => 'courses',
            'endpoint'    => '/CrmToMobileSync/getCourseWWWInfoListData',
            'model'       => Course::class,
            'primaryKey'  => 'coursesHeadingsID',
            'apiPrimaryKey' => 'coursesHeadingsID',
            'pageSize'    => 1000,
            'responseKey' => 'body',
            'extraParams' => [
                'current_LocalizationsID' => '0',
            ],

            'fieldMap' => function (array $r) use ($syncService): array {
                return [
                    'courseHeadingName'           => (string)($r['courseHeadingName'] ?? ''),
                    'startingDate'                => $syncService->validateDate($r['startingDate'] ?? '', null),
                    'closingDate'                 => $syncService->validateDate($r['closingDate'] ?? '', null),
                    'courseDurationInMinutesDVID' => (int)($r['courseDurationInMinutesDVID'] ?? 0),
                    'durationMin'                 => (string)($r['DurationMin'] ?? '0'),
                    'courseFrequencyDVID'         => (int)($r['CourseFrequencyDVID'] ?? 0),
                    'frequency'                   => (string)($r['Frequency'] ?? ''),
                    'websiteStatusesDVID'         => (int)($r['websiteStatusesDVID'] ?? 0),
                    'websiteStatusesName'         => (string)($r['websiteStatusesName'] ?? ''),
                    'cancelled'                   => (int)($r['cancelled'] ?? 0),
                    'courseAgeRangesDVID'         => (int)($r['courseAgeRangesDVID'] ?? 0),
                    'courseAgeRanges'             => (string)($r['courseAgeRanges'] ?? ''),
                    'mainCategoryDID'             => (int)($r['mainCategoryDID'] ?? 0),
                    'mainCategoryName'            => (string)($r['mainCategoryName'] ?? ''),
                    'courseDanceStyleDID'         => (int)($r['courseDanceStyleDID'] ?? 0),
                    'courseDanceStyle'            => (string)($r['courseDanceStyle'] ?? ''),
                    'courseLevelDID'              => (int)($r['courseLevelDID'] ?? 0),
                    'courseLevel'                 => (string)($r['courseLevel'] ?? ''),
                    'instructorEmployeesIDList'   => (string)($r['instructorEmployeesIDList'] ?? ''),
                    'instructorsList'             => (string)($r['instructorsList'] ?? ''),
                    'courseTimeName'              => (string)($r['courseTimeName'] ?? ''),
                    'courseTimeDVID'              => (string)($r['courseTimeDVID'] ?? ''),
                    'courseTimeGrup'              => (string)($r['courseTimeGrup'] ?? ''),
                    'localizationsID'             => (int)($r['localizationsID'] ?? 0),
                    'localizationName'            => (string)($r['localizationName'] ?? ''),
                    'startDateTime'               => $syncService->validateDate($r['startDateTime'] ?? '', null),
                    'eventDate'                   => $syncService->validateDate($r['eventDate'] ?? '', null),
                    'startWeekDaysDVID'           => (int)($r['startWeekDaysDVID'] ?? 0),
                ];
            },

            'afterSave' => function (array $r): void {
                $courseId = (int)($r['coursesHeadingsID'] ?? 0);

                if ($courseId && !empty($r['price']) && is_array($r['price'])) {
                    $apiProductIds = array_values(array_filter(array_column($r['price'], 'productsID')));

                    CoursePrice::where('coursesHeadingsID', $courseId)
                        ->whereNotIn('productsID', $apiProductIds)
                        ->delete();

                    foreach ($r['price'] as $p) {
                        $productId = (int)($p['productsID'] ?? 0);
                        if (!$productId) {
                            continue;
                        }

                        CoursePrice::updateOrCreate(
                            ['productsID' => $productId, 'coursesHeadingsID' => $courseId],
                            [
                                'priceListPositionName' => (string)($p['priceListPositionName'] ?? ''),
                                'amount'                => (int)($p['amount'] ?? 0),
                                'unitAmount'            => (int)($p['unitAmount'] ?? 0),
                            ]
                        );
                    }
                }
            },
        ]);
    }
}