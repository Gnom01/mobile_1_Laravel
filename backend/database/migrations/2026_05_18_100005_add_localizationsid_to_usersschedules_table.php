<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('usersschedules', 'localizationsid')) {
            return;
        }

        Schema::table('usersschedules', function (Blueprint $table) {
            $table->unsignedInteger('localizationsid')->default(0)->after('whoupdated_usersid');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('usersschedules', 'localizationsid')) {
            return;
        }

        Schema::table('usersschedules', function (Blueprint $table) {
            $table->dropColumn('localizationsid');
        });
    }
};
