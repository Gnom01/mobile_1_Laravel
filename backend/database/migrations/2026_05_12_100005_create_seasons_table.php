<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->unsignedInteger('seasonsid')->primary();
            $table->string('seasonname', 255)->nullable();
            $table->date('fromdate')->nullable();
            $table->date('todate')->nullable();
            $table->unsignedInteger('intfromdate')->nullable();
            $table->unsignedInteger('inttodate')->nullable();
            $table->unsignedSmallInteger('fromyear')->nullable();
            $table->unsignedSmallInteger('toyear')->nullable();
            $table->tinyInteger('timestatusdvid')->nullable();
            $table->tinyInteger('hidden')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
