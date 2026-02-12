<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usersrelations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('UsersRelationsID')->unique();
            $table->unsignedInteger('Parent_UsersID');
            $table->unsignedInteger('UsersID');
            $table->unsignedSmallInteger('ParticipantRelationsDVID');
            $table->string('Description', 255)->default('');
            $table->date('DateFrom');
            $table->date('DateTo');
            $table->tinyInteger('Cancelled')->default(0);
            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID');
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID');
            $table->unsignedInteger('LocalizationsID');
            $table->integer('Status');
            $table->index(['UsersID', 'Cancelled'], 'idx_users_cancelled');
            $table->index(['Parent_UsersID', 'Cancelled'], 'idx_parent_users_cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usersrelations');
    }
};
