<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productsdimensions', function (Blueprint $table) {
            $table->unsignedInteger('productsdimensionsid')->primary();
            $table->unsignedInteger('productsid')->nullable();
            $table->unsignedInteger('dictionariesid')->nullable();
            $table->string('dictionaryname', 255)->nullable();
            $table->unsignedSmallInteger('positiondvid')->nullable();
            $table->integer('orderposition')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
            $table->string('valuetext', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productsdimensions');
    }
};
