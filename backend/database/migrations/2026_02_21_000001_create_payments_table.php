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
        Schema::create('payments', function (Blueprint $table) {
            $table->unsignedInteger('paymentsID')->primary(); // primary key from SQL
            $table->integer('clientsID')->default(0);
            $table->unsignedInteger('localizationsID');
            $table->unsignedInteger('usersID')->default(0);
            $table->unsignedInteger('payer_UsersID')->default(0);
            $table->string('cashDesksIK', 255)->default('');
            $table->unsignedInteger('recepcionist_UsersID')->default(0);
            $table->unsignedSmallInteger('paymentMethodsDVID');
            $table->unsignedSmallInteger('paymentStatusesDVID')->default(0);
            $table->date('paymentDate');
            $table->decimal('paymentAmount', 10, 2);
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted');
            $table->unsignedInteger('whoInserted_UsersID')->default(0);
            $table->dateTime('whenUpdated');
            $table->unsignedInteger('whoUpdated_UsersID')->default(0);
            $table->string('referencerNumberTransfe', 255)->default('')->nullable();
            $table->string('buyerNIP', 255)->default('');
            $table->smallInteger('fiscalized')->default(0);
            $table->dateTime('fiscalizedDate')->nullable();
            $table->dateTime('rejectionDate');
            $table->string('reasonForRejection', 255)->default('');
            $table->integer('bulksIDS')->default(0);
            $table->integer('reduceFromWallet_UsersID')->default(0);
            $table->integer('transfer_paymentsID')->default(0);
            $table->unsignedInteger('original_paymentsID')->nullable();
            $table->string('giftcardKey', 255)->default('')->nullable();

            $table->unique(['paymentStatusesDVID', 'cancelled', 'paymentMethodsDVID', 'paymentsID'], 'UK_Payments_paymentStatusDVID');
            $table->index('bulksIDS', 'idx_Payments_bulksIDS');
            $table->index('giftcardKey', 'IDX_payments_giftcardKey');
            $table->index('payer_UsersID', 'idx_payments_payer_usersID');
            $table->index('usersID', 'idx_payments_usersID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
