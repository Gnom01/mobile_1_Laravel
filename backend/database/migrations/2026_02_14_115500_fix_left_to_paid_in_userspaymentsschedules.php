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
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->dropColumn('leftToPaid');
        });
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->decimal('leftToPaid', 10, 2)->storedAs("`paymentAmount` - `amountPaid` - `amountTransferred` - `amountCorrected` ")->after('orderValue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->dropColumn('leftToPaid');
        });
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->decimal('leftToPaid', 10, 2)->unsigned()->storedAs("`paymentAmount` - `amountPaid` - `amountCorrected` ")->after('orderValue');
        });
    }
};
