<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_record_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_log_id')->nullable()->constrained('sync_run_logs')->nullOnDelete();
            $table->string('resource', 255)->index();
            $table->string('record_id', 255)->nullable()->index();
            $table->string('field', 255)->nullable();
            $table->text('original_value')->nullable();
            $table->text('error_message');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_record_failures');
    }
};
