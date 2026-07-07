<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduleseventssettlements', function (Blueprint $table) {
            $table->unsignedBigInteger('schedulesEventsSettlementsID')->autoIncrement()->primary();
            $table->unsignedBigInteger('parent_SchedulesEventsSettlementsID')->default(0);
            $table->unsignedInteger('schedulesID');
            $table->unsignedInteger('master_SchedulesID');
            $table->unsignedInteger('coursesHeadingsID');
            $table->unsignedInteger('localizationsID');
            $table->unsignedInteger('classRoomsID');
            $table->unsignedSmallInteger('weekDaysDVID');
            $table->unsignedInteger('intEventDate');
            $table->unsignedInteger('masterIntEventDate');
            $table->date('eventDate');
            $table->time('timeFrom');
            $table->time('timeTo');
            $table->unsignedSmallInteger('sheduleItemTypeDVID')->default(0);
            $table->string('instructorsIDList', 255)->default('');
            $table->unsignedSmallInteger('eventsSettlementsStatusesDVID')->default(1);
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted')->useCurrent();
            $table->unsignedInteger('whoInserted_UsersID');
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID');
            $table->unsignedInteger('productsLevel2DVID')->default(0);
            $table->dateTime('startDateTime')->storedAs("addtime(`eventDate`,`timeFrom`)")->nullable();
            $table->dateTime('endDateTime')->storedAs("case when `timeFrom` is not null and `timeTo` < `timeFrom` then addtime(date_add(`eventDate`, interval 1 day), `timeTo`) else addtime(`eventDate`,`timeTo`) end")->nullable();
            $table->unsignedSmallInteger('durationInMinutes')->storedAs("case when `timeFrom` is null or `timeTo` is null then null when `timeTo` < `timeFrom` then (time_to_sec(timediff(`timeTo`,`timeFrom`)) / 60) + 1440 else (time_to_sec(timediff(`timeTo`,`timeFrom`)) / 60) end")->nullable();

            $table->unique(
                ['schedulesID', 'cancelled', 'schedulesEventsSettlementsID'],
                'UK_scheduleseventssettlements'
            );
            $table->unique(
                ['eventDate', 'sheduleItemTypeDVID', 'localizationsID', 'cancelled', 'schedulesID', 'durationInMinutes', 'coursesHeadingsID', 'productsLevel2DVID', 'schedulesEventsSettlementsID'],
                'UK_SchedulesEventsSettlements_eventDate'
            );
            $table->index(
                ['master_SchedulesID', 'eventDate', 'masterIntEventDate'],
                'IDX_SchedulesEventsSettlements_masterSchedulesID'
            );
            $table->index(
                ['schedulesID', 'cancelled', 'sheduleItemTypeDVID'],
                'idx_ses_sched_cancel_type'
            );
            $table->index(
                ['schedulesID', 'cancelled', 'sheduleItemTypeDVID', 'masterIntEventDate'],
                'idx_ses_sched_cancel_type_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduleseventssettlements');
    }
};
