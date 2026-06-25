<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
            $table->unsignedInteger('coursesHeadingsID');
            $table->unsignedInteger('productsID')->unique();
            $table->string('priceListPositionName', 255)->default('');
            $table->unsignedInteger('amount')->default(0);
            $table->unsignedInteger('unitAmount')->default(0);

            $table->foreign('coursesHeadingsID')
                ->references('coursesHeadingsID')
                ->on('courses')
                ->onDelete('cascade');

            $table->index('coursesHeadingsID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prices');
    }
};
