<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prośby o powiązanie ISTNIEJĄCEJ osoby (flow "szukaj i powiąż"),
        // weryfikowane kodem SMS. Osobna tabela od otp_requests, żeby kod
        // logowania nie mógł posłużyć do powiązania konta (i odwrotnie).
        Schema::create('relation_link_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('requester_users_id')->index();  // U — zalogowany, wnioskuje o powiązanie
            $table->unsignedInteger('target_users_id');              // P — osoba wyszukana do powiązania
            $table->unsignedInteger('otp_recipient_users_id');       // odbiorca kodu: P albo jego opiekun
            $table->string('otp_recipient_phone', 25)->index();      // numer Z BAZY w chwili wysyłki (audyt + rate limit)
            $table->smallInteger('participant_relations_dvid');      // rola U wobec P (ValueID słownika ParticipantRelations)
            $table->string('code_hash', 255);
            $table->dateTime('expires_at');
            $table->tinyInteger('attempts')->default(0);
            $table->string('sent_message_id', 100)->nullable();
            $table->string('status', 20)->default('pending');        // pending | verified
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relation_link_requests');
    }
};
