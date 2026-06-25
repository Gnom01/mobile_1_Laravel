<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('days', function (Blueprint $table) {
            $table->unsignedInteger('localizationsid')->nullable()->after('whoupdated_usersid');
        });

        Schema::table('productspaymentinstallments', function (Blueprint $table) {
            $table->unsignedInteger('localizationsid')->nullable()->after('whoupdated_usersid');
        });
    }

    public function down(): void
    {
        Schema::table('days', function (Blueprint $table) {
            $table->dropColumn('localizationsid');
        });

        Schema::table('productspaymentinstallments', function (Blueprint $table) {
            $table->dropColumn('localizationsid');
        });
    }
};
