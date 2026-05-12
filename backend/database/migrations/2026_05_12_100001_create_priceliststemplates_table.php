<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priceliststemplates', function (Blueprint $table) {
            $table->unsignedInteger('priceliststemplatesid')->primary();
            $table->unsignedInteger('settlementsrulesid')->nullable();
            $table->unsignedInteger('contractspatternsid')->nullable();
            $table->string('pricelisttemplatename', 255)->nullable();
            $table->date('expirationdatefrom')->nullable();
            $table->date('expirationdateto')->nullable();
            $table->string('description', 255)->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
            $table->tinyInteger('paymentdvid')->nullable();
            $table->unsignedSmallInteger('templatesstatusesdvid')->nullable();
            $table->unsignedInteger('transferid')->nullable();
            $table->unsignedInteger('employeesid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priceliststemplates');
    }
};
