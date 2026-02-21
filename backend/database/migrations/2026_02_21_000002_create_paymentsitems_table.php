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
        Schema::create('paymentsitems', function (Blueprint $table) {
            $table->unsignedInteger('paymentsItemsID')->primary();
            $table->unsignedInteger('clientsID');
            $table->unsignedInteger('localizationsID');
            $table->unsignedInteger('usersID')->default(0);
            $table->unsignedInteger('payer_usersID')->default(0);
            $table->unsignedInteger('usersBasketsID')->default(0);
            $table->unsignedInteger('usersPaymentsSchedulesID')->default(0);
            $table->unsignedInteger('contractsID')->default(0);
            $table->unsignedInteger('productsID')->default(0);
            $table->date('paymentDate')->nullable();
            $table->string('itemName', 255);
            $table->decimal('productUnitPrice', 10, 2);
            $table->decimal('productQuantity', 10, 4);
            $table->string('productUnitIK', 10);
            $table->decimal('paymentItemAmount', 10, 2);
            $table->decimal('vatAmount', 10, 2);
            $table->string('vatRatesIK', 10);
            $table->char('ptu', 1);
            $table->unsignedInteger('paymentsID');
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted');
            $table->unsignedInteger('whoInserted_UsersID')->default(0);
            $table->dateTime('whenUpdated');
            $table->unsignedInteger('whoUpdated_UsersID')->default(0);
            $table->string('descriptions', 255)->default('');
            $table->unsignedInteger('tranfer_PaymentsItemsID')->default(0);
            $table->unsignedInteger('reduceFromWallet_UsersID')->default(0);

            $table->unique(['usersID', 'payer_usersID', 'cancelled', 'usersPaymentsSchedulesID', 'paymentsID', 'paymentItemAmount', 'paymentsItemsID'], 'IDX_paymentsitems_usersID');
            $table->index(['contractsID', 'cancelled', 'paymentsID', 'paymentItemAmount'], 'IDX_PaymentsItems_contractsID');
            $table->index(['paymentsID', 'cancelled'], 'IDX_PaymentsItems_paymentrsID');
            $table->index('paymentsID', 'IDX_paymentsitems_paymentsID');
            $table->index(['usersPaymentsSchedulesID', 'cancelled', 'paymentsID', 'paymentItemAmount'], 'IDX_paymentsitems_usersPaymentsSchedulesID');
            $table->index(['usersPaymentsSchedulesID', 'cancelled'], 'idx_sched_cancel');
            $table->index('usersPaymentsSchedulesID', 'idx_usersPaymentsSchedulesID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paymentsitems');
    }
};
