<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_states', function (Blueprint $table) {
            $table->unsignedBigInteger('last_synced_id')->default(0)->after('cursor');
        });
    }

    public function down(): void
    {
        Schema::table('sync_states', function (Blueprint $table) {
            $table->dropColumn('last_synced_id');
        });
    }
};
