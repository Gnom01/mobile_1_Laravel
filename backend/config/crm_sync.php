<?php

use App\Models\CoursesHeading;
use App\Models\SchedulesEventSettlement;
use App\Models\UsersSchedule;
use App\Models\UsersProduct;

return [
    'default_buffer_seconds' => 5,
    'default_page_size' => 1000,
    'delay_warning_minutes' => 15,
    'lock_store' => env('CRM_SYNC_LOCK_STORE', 'database'),

    /*
     * Resource descriptors are the contract for every CRM -> mobile table.
     * New tables must be added here before they are scheduled.
     */
    'resources' => [
        'coursesheadings' => [
            'job' => App\Jobs\PullCoursesHeadingsJob::class,
            'source_table' => 'CoursesHeadings',
            'target_table' => 'coursesheadings',
            'endpoint' => '/CrmToMobileSync/getCoursesHeadingsMobile',
            'model' => CoursesHeading::class,
            'primary_key' => 'CoursesHeadingsID',
            'api_primary_key' => 'coursesheadingsid',
            'full_sync_id_field' => 'coursesheadingsid',
            'incremental_fields' => ['whenupdated', 'wheninserted'],
            'timestamp_field' => 'whenupdated',
            'date_columns' => ['startingdate', 'closingdate', 'expirationdate', 'paymentdate', 'wheninserted', 'whenupdated'],
            'nullable_columns' => ['StartingDate', 'ClosingDate', 'ExpirationDate', 'PaymentDate'],
            'required_columns' => ['CoursesHeadingsID'],
            'soft_delete_columns' => ['cancelled', 'hidden'],
            'field_mapping' => 'raw',
            'page_size' => 1000,
            'extra_params' => ['current_LocalizationsID' => '0'],
            'mode' => 'full_then_incremental',
        ],

        'scheduleseventssettlements' => [
            'job' => App\Jobs\PullSchedulesEventsSettlementsJob::class,
            'source_table' => 'SchedulesEventsSettlements',
            'target_table' => 'scheduleseventssettlements',
            'endpoint' => '/CrmToMobileSync/getSchedulesEventsSettlementsMobile',
            'model' => SchedulesEventSettlement::class,
            'primary_key' => 'scheduleseventssettlementsid',
            'api_primary_key' => 'scheduleseventssettlementsid',
            'full_sync_id_field' => 'scheduleseventssettlementsid',
            'incremental_fields' => ['whenupdated', 'wheninserted'],
            'timestamp_field' => 'whenupdated',
            'date_columns' => ['eventdate', 'wheninserted', 'whenupdated'],
            'nullable_columns' => [],
            'required_columns' => ['scheduleseventssettlementsid', 'eventdate'],
            'soft_delete_columns' => ['cancelled'],
            'field_mapping' => 'job',
            'page_size' => 1000,
            'extra_params' => ['current_LocalizationsID' => '0'],
            'max_execution_time' => 900,
            'lock_seconds' => 1800,
            'progress_log_every' => 50,
            'mode' => 'full_then_incremental',
        ],

        'usersschedules' => [
            'job' => App\Jobs\PullUsersSchedulesJob::class,
            'source_table' => 'UsersSchedules',
            'target_table' => 'usersschedules',
            'endpoint' => '/CrmToMobileSync/getUsersSchedulesMobile',
            'model' => UsersSchedule::class,
            'primary_key' => 'usersschedulesid',
            'api_primary_key' => 'usersschedulesid',
            'full_sync_id_field' => 'usersschedulesid',
            'incremental_fields' => ['whenupdated', 'wheninserted'],
            'timestamp_field' => 'whenupdated',
            'date_columns' => ['validfromdate', 'validtodate', 'wheninserted', 'whenupdated'],
            'nullable_columns' => ['validfromdate', 'validtodate', 'wheninserted', 'whenupdated'],
            'required_columns' => ['usersschedulesid'],
            'soft_delete_columns' => ['cancelled'],
            'field_mapping' => 'raw',
            'page_size' => 1000,
            'extra_params' => ['current_LocalizationsID' => '0'],
            'mode' => 'full_then_incremental',
        ],

        'usersproducts' => [
            'job' => App\Jobs\PullUsersProductsJob::class,
            'source_table' => 'UsersProducts',
            'target_table' => 'usersproducts',
            'endpoint' => '/CrmToMobileSync/getUsersProductsMobile',
            'model' => UsersProduct::class,
            'primary_key' => 'usersproductsid',
            'api_primary_key' => 'usersproductsid',
            'full_sync_id_field' => 'usersproductsid',
            'incremental_fields' => ['whenupdated', 'wheninserted'],
            'timestamp_field' => 'whenupdated',
            'date_columns' => ['validfromdate', 'validtodate', 'wheninserted', 'whenupdated'],
            'nullable_columns' => ['validfromdate', 'validtodate', 'durationinminutes'],
            'required_columns' => ['usersproductsid'],
            'soft_delete_columns' => ['cancelled'],
            'field_mapping' => 'job',
            'page_size' => 1000,
            'extra_params' => ['current_LocalizationsID' => '0'],
            'mode' => 'full_then_incremental',
        ],
    ],
];
