<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('platform', 20);
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('device_id', 120)->nullable()->index();
            $table->string('app_version', 50)->nullable();
            $table->string('locale', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('push_segments', function (Blueprint $table) {
            $table->id();
            $table->string('crm_id', 100)->nullable()->index();
            $table->string('name', 255);
            $table->json('filters_json')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->nullable()->unique();
            $table->string('title', 255);
            $table->text('body');
            $table->string('category', 50)->index();
            $table->string('type', 50)->nullable()->index();
            $table->string('priority', 20)->default('normal');
            $table->text('image_url')->nullable();
            $table->text('deep_link')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('created_by_crm_user_id')->nullable()->index();
            $table->foreignId('push_segment_id')->nullable()->constrained('push_segments')->nullOnDelete();
            $table->json('filters_json')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();
        });

        Schema::create('push_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_notification_id')->constrained('push_notifications')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreignId('device_token_id')->nullable()->constrained('device_tokens')->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->string('provider_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['push_notification_id', 'user_id', 'device_token_id'], 'push_recipient_device_unique');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_recipients');
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('push_segments');
        Schema::dropIfExists('device_tokens');
    }
};
