<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\CrmSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullTicketsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'      => 'tickets',
            'endpoint'      => '/CrmToMobileSync/getTicketListData',
            'model'         => Ticket::class,
            'primaryKey'    => 'crm_id',
            'apiPrimaryKey' => 'coursesHeadingsID',
            'pageSize'      => 500,
            'responseKey'   => 'body',
            'extraParams'   => [
                'current_LocalizationsID' => '0',
            ],

            'fieldMap' => function (array $r) use ($syncService): array {
                return [
                    'courses_headings_id' => (int)    ($r['coursesHeadingsID']     ?? 0),
                    'products_id'         => (int)    ($r['productsID']            ?? 0),
                    'title'               => (string) ($r['courseHeadingName']     ?? ''),
                    'description'         => (string) ($r['description']           ?? ''),
                    'offer_type'          => 'ticket',
                    'website_status_id'   => (int)    ($r['websiteStatusesDVID']   ?? 0),
                    'is_closed'           => (int)    ($r['isClosed']              ?? 0),
                    'starts_at'           => $syncService->validateDate($r['startingDate']     ?? '', null),
                    'ends_at'             => $syncService->validateDate($r['closingDate']      ?? '', null),
                    'localization_id'     => (int)    ($r['localizationsID']        ?? 0),
                    'localization_name'   => (string) ($r['localizationName']       ?? ''),
                    'age_range_id'        => (int)    ($r['courseAgeRangesDVID']    ?? 0),
                    'age_range_name'      => (string) ($r['courseAgeRanges']        ?? ''),
                    'category_id'         => (int)    ($r['mainCategoryDID']        ?? 0),
                    'category_name'       => (string) ($r['mainCategoryName']       ?? ''),
                    'level_id'            => (int)    ($r['courseLevelDID']         ?? 0),
                    'level_name'          => (string) ($r['courseLevel']            ?? ''),
                    'style_id'            => (int)    ($r['courseDanceStyleDID']    ?? 0),
                    'style_name'          => (string) ($r['courseDanceStyle']       ?? ''),
                    'instructors'         => (string) ($r['instructorsList']        ?? ''),
                    'next_event_date'     => $syncService->validateDate($r['eventDate']        ?? '', null),
                    'start_time'          => (string) ($r['courseTimeName']         ?? ''),
                    'available_places'    => (int)    ($r['availablePlaces']        ?? 0),
                    'capacity'            => (int)    ($r['capacity']               ?? 0),
                    'event_id'            => (int)    ($r['eventID']                ?? 0),
                    'ticket_type'         => (string) ($r['ticketType']             ?? ''),
                    'price_from'          => (float)  ($r['priceFrom']              ?? 0),
                    'sale_starts_at'      => $syncService->validateDate($r['saleStartsAt']     ?? '', null),
                    'sale_ends_at'        => $syncService->validateDate($r['saleEndsAt']       ?? '', null),
                    'raw_crm_payload'     => json_encode($r),
                    'crm_updated_at'      => $syncService->validateDate($r['whenUpdated']      ?? '', null),
                ];
            },
        ]);
    }
}
