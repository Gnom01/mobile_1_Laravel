<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tymczasowe rezerwacje sal (hold 15 min) tworzone przez instruktora.
        // Żyją w bazie mobilnej i nakładają się na zajętość czytaną z CRM (SES).
        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('instructor_users_id')->index();
            $table->unsignedInteger('localizations_id')->index();
            $table->unsignedInteger('class_rooms_id')->index();
            $table->date('reservation_date');
            $table->time('time_from');
            $table->time('time_to');
            $table->string('mode', 12)->default('exclusive'); // exclusive | shared
            // pending_payment | confirmed | partially_paid | expired | cancelled
            $table->string('status', 20)->default('pending_payment');
            $table->dateTime('expires_at')->index();
            $table->unsignedInteger('product_id')->nullable(); // CRM productsID cennika
            $table->unsignedInteger('crm_xschedules_id')->nullable(); // po potwierdzeniu
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            // Szybkie wyszukiwanie kolidujących holdów na salę i dzień.
            $table->index(['class_rooms_id', 'reservation_date', 'status'], 'idx_room_date_status');
        });

        Schema::create('room_reservation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_reservation_id')->index();
            $table->unsignedInteger('users_id')->index();
            $table->unsignedInteger('users_payments_schedules_id')->nullable(); // naliczenie CRM (kolejny etap)
            $table->string('payment_url', 500)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('room_reservation_id')
                ->references('id')->on('room_reservations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_reservation_participants');
        Schema::dropIfExists('room_reservations');
    }
};
