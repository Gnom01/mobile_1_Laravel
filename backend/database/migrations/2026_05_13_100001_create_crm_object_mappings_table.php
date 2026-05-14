<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_object_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid');
            $table->string('local_table', 100);
            $table->unsignedBigInteger('local_id');
            $table->string('crm_table', 100);
            $table->unsignedBigInteger('crm_id');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('guid');
            $table->unique(['crm_table', 'crm_id'], 'uq_crm_object_mappings_crm');
            $table->unique(['local_table', 'local_id'], 'uq_crm_object_mappings_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_object_mappings');
    }
};
