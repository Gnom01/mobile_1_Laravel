<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xschedules', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('roomsids')->nullable();
            $table->string('instructors', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('groupname', 255)->nullable();
            $table->dateTime('starttime')->nullable();
            $table->dateTime('endtime')->nullable();
            $table->string('description', 255)->nullable();
            $table->tinyInteger('isallday')->nullable();
            $table->string('recurrencerule', 255)->nullable();
            $table->string('categorycolor', 255)->nullable();
            $table->string('leftcolor', 255)->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->smallInteger('weekdaysdvid')->nullable();
            $table->string('recurrenceexception', 2000)->nullable();
            $table->unsignedInteger('coursesheadingsid')->nullable();
            $table->unsignedInteger('productsid')->nullable();
            $table->date('repeatuntildate')->nullable();
            $table->time('timefrom')->nullable();
            $table->time('timeto')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
            $table->unsignedInteger('exceptionintday')->nullable();
            $table->date('startdate')->nullable();
            $table->integer('sheduleitemtypedvid')->nullable();
            $table->tinyInteger('excludedfromweeklyschedule')->nullable();
            $table->unsignedInteger('metarecurrenceid')->nullable();
            $table->unsignedTinyInteger('isworkoff')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xschedules');
    }
};
