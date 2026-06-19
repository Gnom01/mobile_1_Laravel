<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Czaty grupowe instruktora.
 *
 * W odróżnieniu od `contact_messages` (wątek 1:1 uczestnik↔instruktor),
 * tutaj instruktor zakłada NAZWANY czat z dowolnym podzbiorem uczestników
 * swoich grup (np. „zaznaczam 5 osób z zespołu i tworzę czat").
 *
 *  - instructor_chats          — nagłówek czatu (właściciel = instruktor, nazwa)
 *  - instructor_chat_members   — uczestnicy czatu (UsersID)
 *  - instructor_chat_messages  — wiadomości
 *  - instructor_chat_reads     — znaczniki przeczytania per użytkownik
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('instructor_user_id')->index();
            $table->string('name', 120);
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();
        });

        Schema::create('instructor_chat_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chat_id')->index();
            $table->unsignedInteger('user_id')->index();
            // 'instructor' | 'participant' | 'parent'
            $table->string('role', 20)->default('participant');
            $table->timestamps();

            $table->unique(['chat_id', 'user_id'], 'uk_instructor_chat_member');
        });

        Schema::create('instructor_chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chat_id')->index();
            $table->unsignedInteger('sender_user_id')->index();
            $table->string('sender_role', 20)->default('instructor');
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
        });

        Schema::create('instructor_chat_reads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('chat_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'user_id'], 'uk_instructor_chat_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_chat_reads');
        Schema::dropIfExists('instructor_chat_messages');
        Schema::dropIfExists('instructor_chat_members');
        Schema::dropIfExists('instructor_chats');
    }
};
