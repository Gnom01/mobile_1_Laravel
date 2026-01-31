<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

            $table->unsignedInteger('ClientsID')->primary();
            $table->unsignedInteger('Parent_ClientsID')->default(0);
            $table->string('GUID', 100)->default('');
            $table->string('ClientName', 255);
            $table->string('NIP', 255)->default('');
            $table->string('DIK', 50)->default('');
            $table->string('City', 255)->default('');
            $table->string('ZipCode', 30)->default('');
            $table->string('Address', 255)->default('');
            $table->double('Longitude')->default(0);
            $table->double('Latitude')->default(0);
            $table->string('Phone', 50)->default('');
            $table->string('Logo', 500)->default('');
            $table->string('URL', 255)->default('');
            $table->string('EMAIL', 255)->default('');
            $table->unsignedInteger('TransferID')->default(0);
            $table->tinyInteger('Cancelled')->default(0);
            $table->smallInteger('Admin')->default(0);

            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);

            $table->string('Regon', 255)->default('');
            $table->string('ContractHeader', 2000)->default('');
            $table->string('ClientsCyti', 255)->default('');
            $table->string('P24_Login', 50);
            $table->string('P24_CRC', 50);
            $table->string('P24_Reports', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
