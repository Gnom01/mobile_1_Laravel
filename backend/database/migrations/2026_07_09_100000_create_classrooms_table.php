<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirror tabeli CRM ClassRooms (sale taneczne per lokalizacja).
        // Synchronizowane przez PullClassRoomsJob (/CrmToMobileSync/getClassRoomsMobile).
        Schema::create('classrooms', function (Blueprint $table) {
            $table->unsignedInteger('classRoomsID')->primary(); // ID z CRM
            $table->string('classRoomName', 255)->default('');
            $table->string('description', 255)->nullable();
            $table->integer('orderPosition')->default(0);
            $table->unsignedInteger('localizationsID')->default(0)->index();
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('whenInserted')->nullable();
            $table->unsignedInteger('whoInserted_UsersID')->nullable();
            $table->dateTime('whenUpdated')->nullable();
            $table->unsignedInteger('whoUpdated_UsersID')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
