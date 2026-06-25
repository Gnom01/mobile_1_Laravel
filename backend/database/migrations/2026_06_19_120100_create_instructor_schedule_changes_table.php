<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zgłoszone przez instruktora zmiany w harmonogramie (np. odwołanie zajęć).
 *
 * Tworzone w kreatorze „+": multiselect typów zmian + multiselect grup + data.
 * Po zapisie leci push do wszystkich uczestników wybranych grup oraz do
 * menadżera szkoły (patrz App\Support\SchoolManagerResolver).
 *
 * To rejestr po stronie aplikacji mobilnej. Docelowo zmiana powinna też
 * trafić do CRM (scheduleseventssettlements / eventsSettlementsStatusesDVID) —
 * patrz ANALIZA_aplikacja_mobilna.md, sekcja braków CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_schedule_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('instructor_user_id')->index();
            // Lista kluczy typów zmian (np. ["cancellation","reschedule"]).
            $table->json('change_types');
            // Lista coursesHeadingsID grup objętych zmianą.
            $table->json('group_ids');
            $table->date('event_date')->index();
            $table->string('title', 160)->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('manager_notified_count')->default(0);
            // Status synchronizacji z CRM: pending | synced | skipped
            $table->string('crm_status', 20)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_schedule_changes');
    }
};
