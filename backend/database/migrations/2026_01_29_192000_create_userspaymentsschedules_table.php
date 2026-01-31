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
        Schema::create('userspaymentsschedules', function (Blueprint $table) {
            $table->unsignedInteger('usersPaymentsSchedulesID')->primary();
            $table->unsignedInteger('usersID')->default(0);
            $table->unsignedInteger('contractsID')->default(0);
            $table->unsignedInteger('productsID')->default(0);
            $table->unsignedInteger('coursesHeadingsID')->default(0);
            $table->unsignedSmallInteger('instalmentNumber')->default(0);
            $table->unsignedSmallInteger('contractInstalmentNumber')->default(0);
            $table->tinyInteger('voidInstalment')->default(0);
            $table->string('positionName', 255);
            $table->string('productAvailableFromDate', 255);
            $table->string('productAvailableToDate', 255)->default('');
            $table->tinyInteger('lessonsAreCounted')->default(0);
            $table->unsignedSmallInteger('lessonsRemainingForUse')->default(0);
            $table->date('paymentDate');
            $table->decimal('paymentAmount', 10, 2)->default(0.00);
            $table->unsignedSmallInteger('paymentStatusesDVID')->default(1);
            $table->string('paymentMethodDVIDList', 255)->default('0');
            $table->decimal('amountPaid', 10, 2)->default(0.00);
            $table->decimal('amountTransferred', 10, 2)->default(0.00);
            $table->decimal('amountCorrected', 10, 2)->default(0.00);
            $table->string('comments', 500)->nullable();
            $table->unsignedInteger('localizationsID');
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted');
            $table->unsignedInteger('whoInserted_UsersID')->default(0);
            $table->dateTime('whenUpdated');
            $table->unsignedInteger('whoUpdated_UsersID')->default(0);
            $table->decimal('price', 10, 2)->default(0.00);
            $table->unsignedInteger('usersProductsID');
            $table->date('lastPaymentDate')->nullable();
            $table->smallInteger('processesDVID')->default(0);
            $table->unsignedInteger('payer_UsersID')->default(0);
            $table->string('paymentMethodDVID', 255)->default('');
            
            $table->integer('orderValue')->storedAs("case when `paymentStatusesDVID` = 2 then 1 else 0 end");
            $table->decimal('leftToPaid', 10, 2)->unsigned()->storedAs("`paymentAmount` - `amountPaid` - `amountCorrected` ");


            $table->unique(['usersProductsID', 'whenUpdated', 'usersPaymentsSchedulesID'], 'UsersPaymentsSchedules_UsersProductsID');
            $table->unique(['usersProductsID', 'instalmentNumber', 'usersPaymentsSchedulesID'], 'UsersPaymentsSchedules_UsersProductsIDInstalment');
            $table->unique(['usersID', 'cancelled', 'paymentStatusesDVID', 'paymentDate', 'paymentAmount', 'usersPaymentsSchedulesID'], 'UsersPaymentsSchedules_UsersID');
            
            $table->index(['payer_UsersID', 'cancelled', 'voidInstalment'], 'idx_payerUsers_cancelled_void');
            $table->index(['usersID', 'cancelled', 'paymentStatusesDVID', 'paymentDate'], 'ix_ups_saldo');
            $table->index(['usersProductsID', 'cancelled', 'instalmentNumber', 'productAvailableFromDate', 'productAvailableToDate'], 'ix_ups_product_window');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('userspaymentsschedules');
    }
};
