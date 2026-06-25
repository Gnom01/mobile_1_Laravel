<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('localizations', function (Blueprint $table) {
            $table->unsignedInteger('LocalizationsID')->primary();
            $table->unsignedInteger('ClientsID')->default(0);
            $table->string('LocalizationName', 255);
            $table->string('Address', 255)->default('');
            $table->string('ZipCode', 30)->default('');
            $table->string('City', 50)->default('');
            $table->string('EMail', 100)->default('');
            $table->string('PhoneNumber', 50)->default('');
            $table->tinyInteger('NumberOfClassRooms')->default(0);
            $table->string('Description', 255)->default('');
            $table->tinyInteger('Cancelled')->default(0);
            $table->tinyInteger('Hidden')->default(0);
            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);
            $table->char('LocalizationCode', 20)->default('');
            $table->string('BanckAccountNumber', 50)->default('');
            $table->string('Default_VatRatesIK', 10);
            $table->string('LedgerLocalizationCode', 10)->default('');
            $table->string('SchoolCyti', 255)->default('')->nullable();
            $table->string('P24_Login', 255)->default('')->nullable();
            $table->string('P24_CRC', 255)->default('')->nullable();
            $table->string('P24_Reports', 255)->default('')->nullable();
            $table->integer('TransferID')->default(0);
            $table->string('SelectPaymentForm', 255)->default('')->nullable();
            $table->string('KeyMpayApi', 255)->default('')->nullable();
            $table->string('KeyMpay', 255)->default('')->nullable();
            $table->string('fiskalType', 255)->default('');
            $table->string('eElientIdFiskal', 255)->default('')->nullable();
            $table->string('eClientSecretFiskal', 255)->default('')->nullable();
            $table->string('ePostIdFiscal', 255)->default('')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localizations');
    }
};
