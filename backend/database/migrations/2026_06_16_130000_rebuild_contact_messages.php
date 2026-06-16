<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Przebudowa czatu na wątki zakotwiczone na (uczestnik, instruktor).
 * Dzięki temu rodzic ma dostęp do wątków swoich dzieci i może odpisywać,
 * a wiadomość widzą wszystkie strony (dziecko, rodzic, instruktor).
 *
 * thread_key = "p{participantUserId}-i{instructorUserId}".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_messages');

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('thread_key', 64)->index();
            $table->unsignedInteger('participant_user_id')->index(); // dziecko/dorosły, którego dotyczy wątek
            $table->unsignedInteger('instructor_user_id')->index();
            $table->unsignedInteger('sender_user_id')->index();       // faktyczny autor
            $table->string('sender_role', 20)->default('participant'); // participant | parent | instructor
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('contact_thread_reads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('thread_key', 64);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'thread_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_thread_reads');
        Schema::dropIfExists('contact_messages');
    }
};
