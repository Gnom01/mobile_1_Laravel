<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Żądania usunięcia Konta (część V dokumentu prawnego): po potwierdzeniu
     * kodem SMS logowanie jest blokowane, sesje i tokeny unieważniane,
     * a żądanie trafia do realizacji przez administrację/CRM.
     */
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('UsersID')->index();
            $table->string('status', 20)->default('confirmed'); // confirmed | processed | cancelled
            $table->dateTime('requested_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
