<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('guid')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('payer_user_id')->nullable();

            // Status lifecycle:
            // pending → processing → crm_success → local_synced
            //                     ↘ crm_failed
            //                                   ↘ local_sync_failed
            $table->string('status', 30)->default('pending');

            $table->string('payload_hash', 64);   // SHA-256 hex
            $table->json('payload_json');

            $table->json('crm_response_json')->nullable();
            $table->unsignedInteger('crm_contracts_id')->nullable();
            $table->unsignedInteger('crm_users_products_id')->nullable();
            $table->unsignedInteger('crm_payments_id')->nullable();

            $table->string('payment_session_id', 255)->nullable();
            $table->string('payment_token', 512)->nullable();
            $table->text('payment_url')->nullable();

            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('crm_contracts_id');
            $table->index('user_id');
            $table->index('payer_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_requests');
    }
};