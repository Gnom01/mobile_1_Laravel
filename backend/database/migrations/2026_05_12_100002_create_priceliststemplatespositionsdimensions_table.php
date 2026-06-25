<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priceliststemplatespositionsdimensions', function (Blueprint $table) {
            $table->unsignedInteger('priceliststemplatespositionsdimensionsid')->primary();
            $table->unsignedInteger('priceliststemplatesid')->nullable();
            $table->unsignedInteger('priceliststemplatespositionsid')->nullable();
            $table->unsignedInteger('dictionariesid')->nullable();
            $table->string('dictionaryname', 255)->nullable();
            $table->unsignedSmallInteger('positiondvid')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priceliststemplatespositionsdimensions');
    }
};
