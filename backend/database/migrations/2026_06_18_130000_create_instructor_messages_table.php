<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historia komunikatów push wysłanych przez instruktora (do grupy lub do
 * pojedynczego uczestnika). Bez tej tabeli instruktor nie ma gdzie zobaczyć,
 * co wysłał — push_notifications nie ma informacji o nadawcy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('instructor_user_id')->index();
            $table->string('target', 20);                       // group | participant
            $table->unsignedInteger('group_id')->nullable()->index();
            $table->unsignedInteger('participant_user_id')->nullable();
            $table->string('title', 160);
            $table->text('body');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_messages');
    }
};
