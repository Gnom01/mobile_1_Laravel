<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_api_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->nullable();
            $table->string('endpoint', 512);
            $table->string('method', 10);
            $table->json('request_json')->nullable();
            $table->json('response_json')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('guid');
            $table->index('http_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_api_logs');
    }
};
