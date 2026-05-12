<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_user_id');
            $table->string('type');
            $table->string('status')->default('created');
            $table->unsignedBigInteger('localization_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->json('selected_schedule_ids')->nullable();
            $table->string('crm_session_id')->nullable()->index();
            $table->string('crm_payment_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->json('remote_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sync_refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['crm_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
