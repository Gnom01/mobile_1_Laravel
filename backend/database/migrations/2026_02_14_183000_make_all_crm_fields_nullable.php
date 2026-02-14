<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ===== CLIENTS TABLE =====
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ClientName', 255)->nullable()->change();
            $table->string('P24_Login', 50)->nullable()->change();
            $table->string('P24_CRC', 50)->nullable()->change();
            $table->string('P24_Reports', 50)->nullable()->change();
        });

        // ===== USERS TABLE =====
        Schema::table('users', function (Blueprint $table) {
            $table->string('LastName', 100)->nullable()->change();
        });

        // ===== USERSPAYMENTSSCHEDULES TABLE =====
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            $table->string('positionName', 255)->nullable()->change();
            $table->string('productAvailableFromDate', 255)->nullable()->change();
            $table->unsignedInteger('localizationsID')->nullable()->change();
            $table->dateTime('whenInserted')->nullable()->change();
            $table->dateTime('whenUpdated')->nullable()->change();
            $table->unsignedInteger('usersProductsID')->nullable()->change();
        });

        // ===== USERSRELATIONS TABLE =====
        Schema::table('usersrelations', function (Blueprint $table) {
            $table->unsignedInteger('Parent_UsersID')->nullable()->change();
            $table->unsignedInteger('UsersID')->nullable()->change();
            $table->unsignedSmallInteger('ParticipantRelationsDVID')->nullable()->change();
            // DateFrom and DateTo are already nullable from migration 2026_02_12_185500
            $table->unsignedInteger('WhoInserted_UsersID')->nullable()->change();
            $table->unsignedInteger('WhoUpdated_UsersID')->nullable()->change();
            $table->unsignedInteger('LocalizationsID')->nullable()->change();
            $table->integer('Status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ===== CLIENTS TABLE =====
        Schema::table('clients', function (Blueprint $table) {
            DB::statement('ALTER TABLE clients MODIFY ClientName VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE clients MODIFY P24_Login VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE clients MODIFY P24_CRC VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE clients MODIFY P24_Reports VARCHAR(50) NOT NULL');
        });

        // ===== USERS TABLE =====
        Schema::table('users', function (Blueprint $table) {
            DB::statement('ALTER TABLE users MODIFY LastName VARCHAR(100) NOT NULL');
        });

        // ===== USERSPAYMENTSSCHEDULES TABLE =====
        Schema::table('userspaymentsschedules', function (Blueprint $table) {
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY positionName VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY productAvailableFromDate VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY localizationsID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY whenInserted DATETIME NOT NULL');
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY whenUpdated DATETIME NOT NULL');
            DB::statement('ALTER TABLE userspaymentsschedules MODIFY usersProductsID INT UNSIGNED NOT NULL');
        });

        // ===== USERSRELATIONS TABLE =====
        Schema::table('usersrelations', function (Blueprint $table) {
            DB::statement('ALTER TABLE usersrelations MODIFY Parent_UsersID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY UsersID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY ParticipantRelationsDVID SMALLINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY WhoInserted_UsersID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY WhoUpdated_UsersID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY LocalizationsID INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE usersrelations MODIFY Status INT NOT NULL');
        });
    }
};
