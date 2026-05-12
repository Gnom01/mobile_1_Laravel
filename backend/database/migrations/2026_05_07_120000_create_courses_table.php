<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->unsignedInteger('coursesHeadingsID')->primary();
            $table->string('courseHeadingName', 255)->default('');
            $table->date('startingDate')->nullable();
            $table->date('closingDate')->nullable();
            $table->unsignedSmallInteger('courseDurationInMinutesDVID')->default(0);
            $table->string('durationMin', 10)->default('0');
            $table->unsignedSmallInteger('courseFrequencyDVID')->default(0);
            $table->string('frequency', 100)->default('');
            $table->unsignedSmallInteger('websiteStatusesDVID')->default(0);
            $table->string('websiteStatusesName', 100)->default('');
            $table->unsignedSmallInteger('courseAgeRangesDVID')->default(0);
            $table->string('courseAgeRanges', 100)->default('');
            $table->unsignedInteger('mainCategoryDID')->default(0);
            $table->string('mainCategoryName', 255)->default('');
            $table->unsignedInteger('courseDanceStyleDID')->default(0);
            $table->string('courseDanceStyle', 255)->default('');
            $table->unsignedInteger('courseLevelDID')->default(0);
            $table->string('courseLevel', 255)->default('');
            $table->string('instructorEmployeesIDList', 255)->default('');
            $table->string('instructorsList', 255)->default('');
            $table->string('courseTimeName', 255)->default('');
            $table->string('courseTimeDVID', 50)->default('');
            $table->string('courseTimeGrup', 255)->nullable()->default('');
            $table->unsignedInteger('localizationsID')->default(0);
            $table->string('localizationName', 255)->default('');
            $table->dateTime('startDateTime')->nullable();
            $table->date('eventDate')->nullable();
            $table->unsignedSmallInteger('startWeekDaysDVID')->default(0);

            $table->index('localizationsID');
            $table->index('websiteStatusesDVID');
            $table->index('mainCategoryDID');
            $table->index('courseDanceStyleDID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
