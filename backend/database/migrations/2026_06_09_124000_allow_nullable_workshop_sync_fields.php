<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workshops_ygm', 'workshops_european'] as $table) {
            DB::statement("
                ALTER TABLE {$table}
                    MODIFY courses_headings_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY products_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY title VARCHAR(255) NULL DEFAULT '',
                    MODIFY website_status_id SMALLINT UNSIGNED NULL DEFAULT 0,
                    MODIFY is_closed TINYINT NULL DEFAULT 0,
                    MODIFY localization_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY localization_name VARCHAR(255) NULL DEFAULT '',
                    MODIFY age_range_id SMALLINT UNSIGNED NULL DEFAULT 0,
                    MODIFY age_range_name VARCHAR(100) NULL DEFAULT '',
                    MODIFY category_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY category_name VARCHAR(255) NULL DEFAULT '',
                    MODIFY level_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY level_name VARCHAR(255) NULL DEFAULT '',
                    MODIFY style_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY style_name VARCHAR(255) NULL DEFAULT '',
                    MODIFY start_time VARCHAR(20) NULL DEFAULT '',
                    MODIFY available_places SMALLINT UNSIGNED NULL DEFAULT 0,
                    MODIFY capacity SMALLINT UNSIGNED NULL DEFAULT 0,
                    MODIFY workshop_type VARCHAR(100) NULL DEFAULT '',
                    MODIFY group_id INT UNSIGNED NULL DEFAULT 0,
                    MODIFY workshop_level VARCHAR(100) NULL DEFAULT '',
                    MODIFY enrollment_mode VARCHAR(50) NULL DEFAULT ''
            ");
        }
    }

    public function down(): void
    {
        foreach (['workshops_ygm', 'workshops_european'] as $table) {
            DB::statement("
                ALTER TABLE {$table}
                    MODIFY courses_headings_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY products_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY title VARCHAR(255) NOT NULL DEFAULT '',
                    MODIFY website_status_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY is_closed TINYINT NOT NULL DEFAULT 0,
                    MODIFY localization_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY localization_name VARCHAR(255) NOT NULL DEFAULT '',
                    MODIFY age_range_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY age_range_name VARCHAR(100) NOT NULL DEFAULT '',
                    MODIFY category_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY category_name VARCHAR(255) NOT NULL DEFAULT '',
                    MODIFY level_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY level_name VARCHAR(255) NOT NULL DEFAULT '',
                    MODIFY style_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY style_name VARCHAR(255) NOT NULL DEFAULT '',
                    MODIFY start_time VARCHAR(20) NOT NULL DEFAULT '',
                    MODIFY available_places SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY capacity SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY workshop_type VARCHAR(100) NOT NULL DEFAULT '',
                    MODIFY group_id INT UNSIGNED NOT NULL DEFAULT 0,
                    MODIFY workshop_level VARCHAR(100) NOT NULL DEFAULT '',
                    MODIFY enrollment_mode VARCHAR(50) NOT NULL DEFAULT ''
            ");
        }
    }
};
