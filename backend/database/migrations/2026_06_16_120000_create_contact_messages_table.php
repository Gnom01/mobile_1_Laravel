<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wiadomości czatu uczestnik (rodzic/dziecko) ↔ instruktor.
 * Lekki model async: każda wiadomość to wiersz; wątek identyfikuje
 * conversation_key (posortowana para UsersID).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('conversation_key', 64)->index();
            $table->unsignedInteger('sender_user_id')->index();
            $table->unsignedInteger('recipient_user_id')->index();
            $table->string('sender_role', 20)->default('participant'); // participant | instructor
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
