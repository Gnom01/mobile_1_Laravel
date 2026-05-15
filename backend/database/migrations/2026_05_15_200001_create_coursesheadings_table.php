<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coursesheadings', function (Blueprint $table) {
            $table->unsignedInteger('CoursesHeadingsID')->autoIncrement()->primary();
            $table->unsignedSmallInteger('ProductsLevel1DVID')->default(0);
            $table->unsignedSmallInteger('ProductsLevel2DVID')->default(0);
            $table->unsignedSmallInteger('ProductsLevel3DVID')->default(0);
            $table->unsignedInteger('DimensionsPatternsID')->default(0);
            $table->string('CourseHeadingName', 255);
            $table->string('CourseHeadingShortName', 50)->default('');
            $table->integer('MaxNumberOfPersons')->default(0);
            $table->integer('NumberOfPersons')->default(0);
            $table->date('StartingDate');
            $table->date('ClosingDate');
            $table->tinyInteger('ClosedGroup')->default(0);
            $table->unsignedSmallInteger('CourseStatusesDVID');
            $table->unsignedSmallInteger('WebsiteStatusesDVID');
            $table->date('ExpirationDate');
            $table->unsignedSmallInteger('CourseDurationInMinutesDVID')->default(0);
            $table->integer('CourseFrequencyPerWeek')->default(0);
            $table->unsignedSmallInteger('CourseFrequencyDVID')->default(0);
            $table->string('AccountNumber', 50)->default('');
            $table->string('Description', 255)->default('');
            $table->tinyInteger('Cancelled')->default(0);
            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);
            $table->unsignedInteger('LocalizationsID')->default(0);
            $table->unsignedInteger('Parent_CoursesHeadingsID')->default(0);
            $table->unsignedSmallInteger('WorkshopCoursesTypesDVID')->default(0);
            $table->tinyInteger('Hidden')->default(0);
            $table->tinyInteger('EventCourseStatus')->nullable()->default(0);
            $table->date('PaymentDate')->nullable();

            $table->unique(
                ['LocalizationsID', 'Parent_CoursesHeadingsID', 'Cancelled', 'CoursesHeadingsID'],
                'UK_CoursesHeadings'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coursesheadings');
    }
};
