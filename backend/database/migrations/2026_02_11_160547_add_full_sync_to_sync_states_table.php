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
        Schema::table('sync_states', function (Blueprint $table) {
            $table->boolean('is_full_synced')->default(false)->after('cursor');
            $table->timestamp('full_sync_started_at')->nullable()->after('is_full_synced');
            $table->timestamp('full_sync_completed_at')->nullable()->after('full_sync_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_states', function (Blueprint $table) {
            $table->dropColumn(['is_full_synced', 'full_sync_started_at', 'full_sync_completed_at']);
        });
    }
};
