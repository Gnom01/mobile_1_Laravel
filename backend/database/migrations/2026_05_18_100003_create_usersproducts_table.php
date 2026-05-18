<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usersproducts', function (Blueprint $table) {
            $table->unsignedInteger('usersproductsid')->autoIncrement()->primary();
            $table->unsignedInteger('usersid')->default(0);
            $table->unsignedInteger('productsid')->default(0);
            $table->unsignedSmallInteger('productslevel1dvid')->default(0);
            $table->unsignedSmallInteger('productslevel2dvid')->default(0);
            $table->unsignedSmallInteger('productslevel3dvid')->default(0);
            $table->integer('priceliststemplatespositionsid')->default(0);
            $table->date('validfromdate')->nullable();
            $table->date('validtodate')->nullable();
            $table->integer('contractsid')->default(0);
            $table->unsignedInteger('coursesheadingsid')->default(0);
            $table->unsignedInteger('localizationsid')->default(0);
            $table->integer('pricelistpositionstypesdvid')->default(0);
            $table->unsignedSmallInteger('paymenttypesdvid')->default(0);
            $table->integer('periodsofvaliditydvid')->default(0);
            $table->smallInteger('numberofperiods')->default(0);
            $table->smallInteger('numberofunitsaccount')->default(0);
            $table->unsignedSmallInteger('numberoflessons')->default(0);
            $table->decimal('unitprice', 19, 2)->default(0.00);
            $table->decimal('price', 19, 2)->default(0.00);
            $table->string('vatratesik', 10)->default('');
            $table->smallInteger('paymentmethodsdvid')->default(0);
            $table->string('paymentmethodsdvidlist', 255)->default('');
            $table->string('promotrionsidlist', 255)->default('');
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->default(0);
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->default(0);
            $table->decimal('paymentamount', 19, 2)->default(0.00);
            $table->integer('durationinminutes')->nullable();
            // productsLevels is a stored generated column — defined here so MySQL accepts the schema
            $table->string('productslevels', 255)
                ->storedAs("concat(productslevel1dvid, ',', productslevel2dvid, ',', productslevel3dvid)")
                ->nullable();
            $table->smallInteger('usersproductsstatusdvid')->default(0);
            $table->string('crm_order_guid', 36)->nullable();

            $table->index(['usersid', 'cancelled'], 'IDX_usersproducts_usersid');
            $table->index(['cancelled'], 'idx_usersproducts_cancelled');
            $table->index(['contractsid'], 'idx_usersproducts_contractsid');
            $table->index(['paymenttypesdvid'], 'idx_usersproducts_paymenttypesdvid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usersproducts');
    }
};