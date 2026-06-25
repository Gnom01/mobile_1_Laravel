<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daysoff', function (Blueprint $table) {
            $table->unsignedInteger('daysoffid')->primary();
            $table->date('date')->nullable();
            $table->unsignedInteger('intdate')->nullable();
            $table->tinyInteger('daysofftypesdvid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
            $table->unsignedSmallInteger('seasonsdimensiondvid')->nullable();
            $table->string('description', 255)->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->date('datato')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daysoff');
    }
};
