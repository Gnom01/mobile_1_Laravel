<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Granularne zgody per kanał (część IV pkt 2-3 dokumentu prawnego):
     * marketing e-mail / SMS-MMS / telefon / push oraz profilowanie.
     * Każdy wpis niesie datę ostatniej zmiany (rozliczalność RODO).
     * CRM pozostaje źródłem prawdy dla zagregowanej flagi marketingowej —
     * agregat jest wypychany po każdej zmianie kanału.
     */
    public function up(): void
    {
        Schema::create('consent_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('UsersID');
            $table->string('consent_key', 40); // marketing_email | marketing_sms | marketing_phone | marketing_push | profiling
            $table->boolean('granted')->default(false);
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->unique(['UsersID', 'consent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_channels');
    }
};
