<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->unsignedInteger('ProductsID')->autoIncrement()->primary();
            $table->unsignedSmallInteger('ProductsLevel1DVID')->default(0);
            $table->unsignedSmallInteger('ProductsLevel2DVID')->default(0);
            $table->unsignedSmallInteger('ProductsLevel3DVID')->default(0);
            $table->unsignedInteger('DimensionsPatternsID')->default(0);
            $table->unsignedInteger('CoursesHeadingsID')->default(0);
            $table->unsignedInteger('PriceListsTemplatesID')->default(0);
            $table->unsignedInteger('PriceListsTemplatesPositionsID')->default(0);
            $table->string('ProductName', 255);
            $table->integer('ContractsPatternsID')->default(0);
            $table->integer('PricelistPositionsTypesDVID')->default(0);
            $table->integer('PeriodsOfValidityDVID')->default(0);
            $table->string('NumberOfPeriods', 255)->default('0');
            $table->string('UnitsOfAccountDVID', 255)->default('0');
            $table->string('NumberOfUnitsAccount', 255)->default('0');
            $table->date('StartingDate');
            $table->date('ClosingDate');
            $table->date('ExpirationDate');
            $table->string('AccountNumber', 50)->default('');
            $table->tinyInteger('TemplateValueChanged')->default(0);
            $table->decimal('UnitPrice', 19, 2)->default(0.00);
            $table->decimal('Price', 19, 2)->default(0.00);
            $table->string('VatRatesIK', 10);
            $table->string('Description', 255)->default('');
            $table->tinyInteger('Cancelled')->default(0);
            $table->unsignedInteger('LocalizationsID')->default(0);
            $table->unsignedSmallInteger('NumberOfLessons')->default(0);
            $table->integer('amountCoursesFrom')->nullable()->default(0);
            $table->integer('amountCoursesTo')->nullable()->default(0);
            $table->unsignedSmallInteger('PaymentTypesDVID')->default(0);
            $table->string('PaymentMethodsDVID', 255);
            $table->unsignedSmallInteger('UsersGroupsDVID')->default(0);
            $table->tinyInteger('TemplateValuesChanged')->default(0);
            $table->string('ProductsTypes', 255)->default('');
            $table->smallInteger('ProductsUnitDVID')->nullable();
            $table->string('ProductsNameReceipt', 255)->default('');
            $table->integer('DurationInMinutes')->nullable();
            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);
            $table->string('metaID', 255);
            $table->tinyInteger('hidden')->nullable();
            $table->integer('minOfLessons')->default(0);
            $table->tinyInteger('isDeposit')->nullable();

            $table->index(['AccountNumber', 'LocalizationsID', 'VatRatesIK'], 'IDX_products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
