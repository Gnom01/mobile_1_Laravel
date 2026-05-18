<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('userworkshopsgroups', function (Blueprint $table) {
            $table->unsignedInteger('userworkshopsgroupsid')->autoIncrement()->primary();
            $table->unsignedInteger('usersproductsid')->default(0);
            $table->unsignedInteger('coursesheadingsid')->default(0);
            $table->unsignedInteger('coursesheadingsidribbons')->default(0);
            $table->unsignedInteger('workshopproductwrapperid')->default(0);
            $table->unsignedInteger('usersid')->default(0);
            $table->unsignedInteger('localizationsid')->default(0);
            $table->unsignedInteger('parent_productsid')->default(0);
            $table->tinyInteger('cancelled')->default(0);
            $table->dateTime('wheninserted')->nullable();
            $table->unsignedInteger('whoinserted_usersid')->default(0);
            $table->dateTime('whenupdated')->nullable();
            $table->unsignedInteger('whoupdated_usersid')->default(0);

            $table->index(['usersid', 'cancelled'], 'IDX_userworkshopsgroups_usersid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userworkshopsgroups');
    }
};
