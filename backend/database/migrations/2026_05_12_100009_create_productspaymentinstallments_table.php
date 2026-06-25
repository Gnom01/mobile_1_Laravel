<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productspaymentinstallments', function (Blueprint $table) {
            $table->unsignedInteger('productspaymentinstallmentsid')->primary();
            $table->unsignedInteger('productsid')->nullable();
            $table->decimal('installmentamount', 10, 2)->nullable();
            $table->date('installmentpaymnetdate')->nullable(); // 'paymnet' typo preserved from CRM
            $table->tinyInteger('cancelled')->nullable();
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->nullable();
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productspaymentinstallments');
    }
};
