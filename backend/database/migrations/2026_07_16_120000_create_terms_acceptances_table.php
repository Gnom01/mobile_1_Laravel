<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rejestr akceptacji Regulaminu aplikacji (wymóg § 5 ust. 4 Regulaminu:
     * "Operator utrwala wersję Regulaminu zaakceptowaną przez Użytkownika,
     * datę i sposób akceptacji").
     */
    public function up(): void
    {
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('UsersID')->index();
            $table->string('document_version', 64);
            $table->string('method', 32); // np. registration, in_app_activation
            $table->dateTime('accepted_at');
            $table->timestamps();

            $table->index(['UsersID', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};
