<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictionaries', function (Blueprint $table) {
            if (!Schema::hasColumn('dictionaries', 'localizationsID')) {
                $table->unsignedInteger('localizationsID')->default(0)->after('Hidden');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dictionaries', function (Blueprint $table) {
            if (Schema::hasColumn('dictionaries', 'localizationsID')) {
                $table->dropColumn('localizationsID');
            }
        });
    }
};
