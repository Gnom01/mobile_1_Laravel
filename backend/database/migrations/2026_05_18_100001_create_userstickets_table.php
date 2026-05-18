<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('userstickets', function (Blueprint $table) {
            $table->unsignedInteger('usersticketsid')->primary();
            $table->string('lastname', 100)->default('');
            $table->string('firstname', 100)->default('');
            $table->string('email', 255)->default('');
            $table->integer('localizationsid')->default(0);
            $table->string('phone', 255)->nullable();
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->default(0);
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userstickets');
    }
};
