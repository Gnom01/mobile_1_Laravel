<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workshops_ygm', 'workshops_european'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'parent_courses_headings_id')) {
                    $table->unsignedInteger('parent_courses_headings_id')
                        ->nullable()
                        ->default(0)
                        ->after('courses_headings_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['workshops_ygm', 'workshops_european'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'parent_courses_headings_id')) {
                    $table->dropColumn('parent_courses_headings_id');
                }
            });
        }
    }
};
