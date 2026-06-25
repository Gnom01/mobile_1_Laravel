<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zgłoszenia instruktora do szkoły (np. usterka sali, brak sprzętu,
 * prośba o zastępstwo, wniosek urlopowy). Multiselect typów + grupy + data + opis.
 * Powiadomienie idzie do menadżera szkoły.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('instructor_user_id')->index();
            // Lista kluczy typów zgłoszeń.
            $table->json('report_types');
            // Opcjonalne grupy, których dotyczy zgłoszenie (coursesHeadingsID).
            $table->json('group_ids')->nullable();
            $table->date('event_date')->nullable()->index();
            $table->string('title', 160)->nullable();
            $table->text('description');
            // new | seen | in_progress | resolved
            $table->string('status', 20)->default('new')->index();
            $table->unsignedInteger('manager_notified_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_reports');
    }
};
