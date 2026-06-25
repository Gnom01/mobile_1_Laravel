<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_record_failures', function (Blueprint $table) {
            // How many times this (resource, record_id) has failed to upsert.
            $table->unsignedInteger('attempts')->default(1)->after('error_message');
            // Set once the record is finally synced (or manually resolved).
            $table->timestamp('resolved_at')->nullable()->after('attempts');
            // Fast lookup of the open failure for a record + retry sweeps.
            $table->index(['resource', 'record_id', 'resolved_at'], 'srf_resource_record_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sync_record_failures', function (Blueprint $table) {
            $table->dropIndex('srf_resource_record_resolved_idx');
            $table->dropColumn(['attempts', 'resolved_at']);
        });
    }
};
