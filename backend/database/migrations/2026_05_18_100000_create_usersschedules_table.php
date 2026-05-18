<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usersschedules', function (Blueprint $table) {
            $table->unsignedInteger('usersschedulesid')->autoIncrement()->primary();
            $table->unsignedInteger('usersid')->default(0);
            $table->unsignedInteger('usersproductsid')->default(0);
            $table->unsignedSmallInteger('attendancetypesdvid')->default(0);
            $table->unsignedInteger('coursesheadingsid')->default(0);
            $table->date('eventdate')->nullable();
            $table->time('timefrom')->nullable();
            $table->time('timeto')->nullable();
            $table->unsignedSmallInteger('attendancestatusdvid')->default(0);
            $table->unsignedInteger('scheduleseventssettlementsid')->default(0);
            $table->unsignedInteger('workoff_usersschedulesid')->default(0);
            $table->unsignedInteger('workoff_coursesheadingsid')->default(0);
            $table->date('workoff_eventdate')->nullable();
            $table->time('workoff_timefrom')->nullable();
            $table->time('workoff_timeto')->nullable();
            $table->unsignedSmallInteger('workoff_attendancestatusdvid')->default(0);
            $table->unsignedInteger('workoff_scheduleseventssettlementsid')->default(0);
            $table->tinyInteger('attended')->nullable();
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->default(0);
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->default(0);

            $table->unique(['eventdate', 'cancelled', 'usersschedulesid'], 'UK_UsersSchedules');
            $table->unique(
                ['scheduleseventssettlementsid', 'usersproductsid', 'cancelled', 'usersschedulesid', 'usersid'],
                'UK_UsersSchedules_SchedulesEventSettlementsID'
            );
            $table->unique(
                ['usersid', 'cancelled', 'attendancetypesdvid', 'scheduleseventssettlementsid', 'usersschedulesid'],
                'UK_usersschedules_usersID'
            );
            $table->index(['usersid', 'cancelled'], 'IDX_usersschedules_usersid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usersschedules');
    }
};
