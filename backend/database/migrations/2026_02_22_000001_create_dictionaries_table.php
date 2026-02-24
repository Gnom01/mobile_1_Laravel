
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
        Schema::create('dictionaries', function (Blueprint $table) {
            $table->unsignedInteger('DictionariesID')->primary();
            $table->unsignedInteger('Parent_DictionariesID')->default(0);
            $table->string('Parent_DictionaryName', 255);
            $table->unsignedSmallInteger('Parent_ValueID')->default(0);
            $table->string('DictionaryName', 255);
            $table->string('Name', 255);
            $table->unsignedInteger('ValueID')->default(0);
            $table->string('ValueText', 255)->default('');
            $table->unsignedSmallInteger('OrderPosition')->default(0);
            $table->string('Description', 255)->default('');
            $table->tinyInteger('Editable')->default(1);
            $table->tinyInteger('Cancelled')->default(0);
            $table->dateTime('WhenInserted');
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated');
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);
            $table->string('ItemColor', 10);
            $table->tinyInteger('Hidden')->default(0);

            $table->unique(['Parent_DictionariesID', 'DictionariesID', 'Cancelled'], 'UK_Dictionaries');
            $table->index(['DictionaryName', 'ValueID', 'Name', 'Cancelled'], 'IDX_Dictionaries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dictionaries');
    }
};
