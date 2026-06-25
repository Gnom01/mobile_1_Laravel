<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            $table->string('subtitle', 80);
            $table->string('description', 200);
            $table->string('color_start', 7)->default('#E20613');
            $table->string('color_end', 7)->default('#B0040E');
            $table->string('action_type', 30)->nullable()->index();
            $table->text('action_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_banners');
    }
};
