<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priceliststemplatespositions', function (Blueprint $table) {
            $table->unsignedInteger('priceliststemplatespositionsid')->primary();
            $table->unsignedInteger('priceliststemplatesid')->nullable();
            $table->unsignedInteger('priceliststemplateslevelsid')->nullable();
            $table->unsignedInteger('contractspatternsid')->nullable();
            $table->string('pricelistpositionname', 255)->nullable();
            $table->smallInteger('itemorder')->nullable();
            $table->unsignedSmallInteger('pricelistpositionstypesdvid')->nullable();
            $table->unsignedSmallInteger('periodsofvaliditydvid')->nullable();
            $table->unsignedSmallInteger('numberofperiods')->nullable();
            $table->unsignedSmallInteger('unitsofaccountdvid')->nullable();
            $table->unsignedSmallInteger('numberofunitsaccount')->nullable();
            $table->date('expirationdate')->nullable();
            $table->unsignedSmallInteger('unitdvid')->nullable();
            $table->decimal('numberofunits', 10, 2)->nullable();
            $table->decimal('unitamount', 19, 2)->nullable();
            $table->decimal('amount', 19, 2)->nullable();
            $table->string('vatratesik', 10)->nullable();
            $table->string('description', 255)->nullable();
            $table->tinyInteger('editable')->nullable();
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
            $table->unsignedInteger('localizationsid')->nullable();
            $table->unsignedTinyInteger('onlyformembers')->nullable();
            $table->unsignedSmallInteger('paymenttypesdvid')->nullable();
            $table->unsignedSmallInteger('numberoflessons')->nullable();
            $table->unsignedInteger('transferid')->nullable();
            $table->integer('usersgroupsdvid')->nullable();
            $table->unsignedSmallInteger('tournamentcategorydvid')->nullable();
            $table->integer('minoflessons')->nullable();
            $table->dateTime('datefrom')->nullable();
            $table->dateTime('dateto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priceliststemplatespositions');
    }
};
