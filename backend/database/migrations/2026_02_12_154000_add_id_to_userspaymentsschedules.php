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
            // 1. Drop the primary key from usersPaymentsSchedulesID
            // Note: In some databases (like MySQL), we need to do this carefully.
            // Laravel's dropPrimary() might need the column name or index name.
            $table->dropPrimary();
        });

        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            // 2. Add the new auto-incrementing ID as the primary key
            $table->bigIncrements('id')->first();
            
            // 3. Make usersPaymentsSchedulesID a unique column
            $table->unique('usersPaymentsSchedulesID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->dropUnique(['usersPaymentsSchedulesID']);
            $table->dropColumn('id');
            $table->primary('usersPaymentsSchedulesID');
        });
    }
};
