<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primary key is intdate (YYYYMMDD integer) — used as upsert key
        Schema::create('days', function (Blueprint $table) {
            $table->unsignedInteger('intdate')->primary();
            $table->unsignedInteger('daysid')->nullable();
            $table->date('date')->nullable();
            $table->tinyInteger('weekdaysdvid')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->tinyInteger('hidden')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('intyearmonth')->nullable();
            $table->unsignedTinyInteger('weekofyear')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('days');
    }
};
