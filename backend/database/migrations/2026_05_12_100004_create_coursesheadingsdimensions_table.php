<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coursesheadingsdimensions', function (Blueprint $table) {
            $table->unsignedInteger('coursesheadingsdimensionsid')->primary();
            $table->unsignedInteger('coursesheadingsid')->nullable();
            $table->unsignedInteger('dictionariesid')->nullable();
            $table->string('dictionaryname', 255)->nullable();
            $table->unsignedSmallInteger('positiondvid')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coursesheadingsdimensions');
    }
};
