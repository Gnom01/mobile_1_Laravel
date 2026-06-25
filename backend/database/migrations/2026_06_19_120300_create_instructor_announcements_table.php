<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ogłoszenia dla instruktorów (blok na pulpicie instruktora):
 * nadchodzące wydarzenia, zbiórki, szkolenia, terminy administracyjne.
 *
 * Audience zawężamy lokalizacją (localizations_id = 0 → wszystkie szkoły).
 * Docelowo źródłem może być CRM (sync) — na razie zarządzane lokalnie /
 * przez panel CRM analogicznie do dashboard_banners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_announcements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 120);
            $table->text('body');
            // 'event' | 'meeting' | 'training' | 'admin' | 'info'
            $table->string('kind', 20)->default('info')->index();
            // 0 = wszystkie lokalizacje, inaczej konkretna szkoła.
            $table->unsignedInteger('localizations_id')->default(0)->index();
            $table->dateTime('event_at')->nullable()->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_announcements');
    }
};
