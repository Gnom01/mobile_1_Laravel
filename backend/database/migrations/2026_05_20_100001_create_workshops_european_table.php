<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops_european', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('crm_id')->unique();
            $table->unsignedInteger('courses_headings_id')->default(0);
            $table->unsignedInteger('parent_courses_headings_id')->nullable()->default(0);
            $table->unsignedInteger('products_id')->default(0);
            $table->string('title', 255)->default('');
            $table->text('description')->nullable();
            $table->string('offer_type', 50)->default('workshop_european');
            $table->unsignedSmallInteger('website_status_id')->default(0);
            $table->tinyInteger('is_closed')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('localization_id')->default(0);
            $table->string('localization_name', 255)->default('');
            $table->unsignedSmallInteger('age_range_id')->default(0);
            $table->string('age_range_name', 100)->default('');
            $table->unsignedInteger('category_id')->default(0);
            $table->string('category_name', 255)->default('');
            $table->unsignedInteger('level_id')->default(0);
            $table->string('level_name', 255)->default('');
            $table->unsignedInteger('style_id')->default(0);
            $table->string('style_name', 255)->default('');
            $table->text('instructors')->nullable();
            $table->date('next_event_date')->nullable();
            $table->string('start_time', 20)->default('');
            $table->unsignedSmallInteger('available_places')->default(0);
            $table->unsignedSmallInteger('capacity')->default(0);
            // Workshop-specific fields
            $table->string('workshop_type', 100)->default('');
            $table->unsignedInteger('group_id')->default(0);
            $table->string('workshop_level', 100)->default('');
            $table->string('enrollment_mode', 50)->default('');
            // Sync fields
            $table->json('raw_crm_payload')->nullable();
            $table->dateTime('crm_updated_at')->nullable();
            $table->timestamps();

            $table->index('localization_id');
            $table->index('website_status_id');
            $table->index('category_id');
            $table->index('style_id');
            $table->index('age_range_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshops_european');
    }
};
