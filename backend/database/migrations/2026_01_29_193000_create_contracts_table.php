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
        Schema::create('contracts', function (Blueprint $table) {
            $table->unsignedInteger('contractsID')->primary();
            $table->unsignedInteger('parent_ContractsID')->default(0);
            $table->unsignedInteger('sellingParent_ContractsID')->default(0);
            $table->unsignedSmallInteger('contractsTypesDVID')->default(0);
            $table->string('contractSygnature', 255);
            $table->integer('contractLocalizationOrdinalNumber')->default(0);
            $table->unsignedSmallInteger('contractStatusesDVID')->default(0);
            $table->unsignedInteger('contractsPatternsID')->default(0);
            $table->string('contractPatternName', 255)->default('');
            $table->unsignedInteger('productsID')->default(0);
            $table->string('productName', 255)->default('');
            $table->unsignedInteger('packagesID')->default(0);
            $table->string('packageName', 255)->default('');
            $table->unsignedInteger('coursesHeadingsID')->default(0);
            $table->string('courseHeadingName', 255)->default('');
            $table->date('contracConclusionDate')->nullable();
            $table->date('contractPeriodFrom')->nullable();
            $table->date('contractPeriodTo')->nullable();
            $table->decimal('contractAmount', 10, 2)->nullable()->default(0.00);
            $table->string('contractAmountText', 255)->default('');
            $table->unsignedInteger('usersID')->default(0);
            $table->string('userFirstName', 50)->default('');
            $table->string('userLastName', 50)->default('');
            $table->string('userAddress', 150)->default('');
            $table->string('userPostCode', 15)->default('');
            $table->string('userCity', 50)->default('');
            $table->string('userIdentityNumber', 50)->default('');
            $table->string('userPESEL', 15)->default('');
            $table->unsignedInteger('payer_UsersID')->default(0);
            $table->string('payerFirstName', 50)->default('');
            $table->string('payerLastName', 50)->default('');
            $table->string('payerAddress', 150)->default('');
            $table->string('payerPostCode', 15)->default('');
            $table->string('payerCity', 50)->default('');
            $table->string('payerIdentityNumber', 50)->default('');
            $table->string('payerPESEL', 15)->default('');
            $table->unsignedInteger('localizationsID')->default(0);
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted');
            $table->unsignedInteger('whoInserted_UsersID')->default(0);
            $table->dateTime('whenUpdated');
            $table->unsignedInteger('whoUpdated_UsersID')->default(0);
            $table->unsignedSmallInteger('durationInMinutesDVID')->default(0);
            $table->dateTime('expirationDate');
            $table->string('courseLength', 255)->default('');
            $table->string('courseLengthWeek', 255)->default('');
            $table->decimal('installmentValueZero', 19, 2)->default(0.00);
            $table->decimal('entryFee', 19, 2)->default(0.00);
            $table->decimal('sumOfInitialCharges', 19, 2)->default(0.00);
            $table->string('paymentName', 255)->default('');
            $table->smallInteger('numberOfFullInstallments')->default(0);
            $table->decimal('monthlyInstallment', 19, 2)->default(0.00);
            $table->string('userPhone', 25)->default('');
            $table->string('userEmail', 255)->default('');
            $table->string('payerPhone', 25)->default('');
            $table->string('payerEmail', 255)->default('');
            $table->unsignedInteger('contractsBulksIDS');
            $table->string('note', 255)->nullable()->default('');
            $table->unique(['usersID', 'contractStatusesDVID', 'contractsTypesDVID', 'cancelled', 'expirationDate', 'contractsID'], 'UK_Contracts');
            $table->index('usersID', 'IDX_contracts_usersID');
            $table->index(['coursesHeadingsID', 'cancelled', 'contractStatusesDVID', 'expirationDate'], 'idx_contracts_optimized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
