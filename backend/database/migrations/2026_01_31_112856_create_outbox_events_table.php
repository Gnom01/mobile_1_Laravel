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
        Schema::create('outbox_events', function (Blueprint $table) {
        $table->id();
        $table->string('entity'); // 'clients' | 'users'
        $table->string('action'); // 'updated' (na start), potem 'created','deleted'
        $table->unsignedBigInteger('local_id');
        $table->json('payload');
        $table->string('idempotency_key')->unique();
        $table->string('status')->default('pending'); // pending|sent|failed
        $table->unsignedInteger('attempts')->default(0);
        $table->text('last_error')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamps();

        $table->index(['status', 'created_at']);
    });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
