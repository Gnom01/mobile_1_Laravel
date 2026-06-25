<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('localizations', function (Blueprint $table) {
            if (Schema::hasColumn('localizations', 'SchoolCyti')) {
                $table->dropColumn('SchoolCyti');
            }
        });
    }

    public function down(): void
    {
        Schema::table('localizations', function (Blueprint $table) {
            if (!Schema::hasColumn('localizations', 'SchoolCyti')) {
                $table->string('SchoolCyti', 255)->default('')->nullable();
            }
        });
    }
};
