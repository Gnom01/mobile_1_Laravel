<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scheduleseventssettlements')) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `scheduleseventssettlements`
                MODIFY `instructorsIDList` VARCHAR(255) NULL DEFAULT NULL,
                MODIFY `endDateTime` DATETIME
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN `timeFrom` IS NOT NULL AND `timeTo` < `timeFrom`
                                THEN ADDTIME(DATE_ADD(`eventDate`, INTERVAL 1 DAY), `timeTo`)
                            ELSE ADDTIME(`eventDate`, `timeTo`)
                        END
                    ) STORED,
                MODIFY `durationInMinutes` SMALLINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN `timeFrom` IS NULL OR `timeTo` IS NULL
                                THEN NULL
                            WHEN `timeTo` < `timeFrom`
                                THEN (TIME_TO_SEC(TIMEDIFF(`timeTo`, `timeFrom`)) / 60) + 1440
                            ELSE (TIME_TO_SEC(TIMEDIFF(`timeTo`, `timeFrom`)) / 60)
                        END
                    ) STORED
            SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('scheduleseventssettlements')) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `scheduleseventssettlements`
                MODIFY `instructorsIDList` VARCHAR(255) NOT NULL DEFAULT '',
                MODIFY `endDateTime` DATETIME
                    GENERATED ALWAYS AS (ADDTIME(`eventDate`, `timeTo`)) STORED,
                MODIFY `durationInMinutes` SMALLINT UNSIGNED
                    GENERATED ALWAYS AS ((TIME_TO_SEC(TIMEDIFF(`timeTo`, `timeFrom`)) / 60)) STORED
            SQL);
    }
};
