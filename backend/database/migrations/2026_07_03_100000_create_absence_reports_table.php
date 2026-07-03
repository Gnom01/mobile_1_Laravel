<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zgłoszenia nieobecności na zajęciach przez rodzica/uczestnika (CLS_04).
 * Zapis lokalny + zdarzenie w outbox_events do przyszłej synchronizacji z CRM
 * (PushOutboxJob). Duplikaty blokowane per (zdarzenie, uczestnik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Kto zgłosił (zalogowany użytkownik — rodzic lub pełnoletni uczestnik).
            $table->unsignedInteger('reporter_user_id')->index();
            // Kogo dotyczy nieobecność.
            $table->unsignedInteger('participant_user_id')->index();
            $table->unsignedInteger('schedules_events_settlements_id')->index();
            $table->date('event_date');
            $table->string('time_from', 8)->nullable();
            $table->string('time_to', 8)->nullable();
            $table->string('course_title', 255)->nullable();
            $table->text('reason')->nullable();
            // reported | synced | cancelled
            $table->string('status', 20)->default('reported')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['participant_user_id', 'schedules_events_settlements_id'],
                'absence_unique_participant_event'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_reports');
    }
};
