<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 255)->index();
            $table->string('mode', 32)->nullable();
            $table->string('status', 32)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('last_synced_id_before')->default(0);
            $table->unsignedBigInteger('last_synced_id_after')->default(0);
            $table->timestamp('last_sync_at_before')->nullable();
            $table->timestamp('last_sync_at_after')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_run_logs');
    }
};
