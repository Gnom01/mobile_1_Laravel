<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 25)->index();
            $table->string('code_hash', 255);
            $table->dateTime('expires_at');
            $table->tinyInteger('attempts')->default(0);
            $table->string('sent_message_id', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};
