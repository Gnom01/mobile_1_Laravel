<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `scheduleseventssettlements` jest synchronizowane raw-passthrough (SELECT ses.*),
 * a CrmSyncService::normalizeValue zamienia puste stringi z CRM na NULL. Te
 * kolumny były NOT NULL bez DEFAULT → rekord z pustym timeFrom/timeTo/classRoomsID
 * itp. blokował insert (poison → po 5 próbach utrata). Czynimy je nullable.
 * Klucze rdzeniowe (schedulesID, coursesHeadingsID, localizationsID, eventDate)
 * zostawiamy NOT NULL — rekord bez nich i tak byłby bezużyteczny.
 */
return new class extends Migration
{
    // kolumna => typ (zachowany z migracji tworzącej)
    private array $columns = [
        'master_SchedulesID'   => 'INT UNSIGNED',
        'classRoomsID'         => 'INT UNSIGNED',
        'weekDaysDVID'         => 'SMALLINT UNSIGNED',
        'intEventDate'         => 'INT UNSIGNED',
        'masterIntEventDate'   => 'INT UNSIGNED',
        'timeFrom'             => 'TIME',
        'timeTo'               => 'TIME',
        'whoInserted_UsersID'  => 'INT UNSIGNED',
        'WhoUpdated_UsersID'   => 'INT UNSIGNED',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('scheduleseventssettlements')) {
            return;
        }
        foreach ($this->columns as $col => $type) {
            if (Schema::hasColumn('scheduleseventssettlements', $col)) {
                DB::statement("ALTER TABLE `scheduleseventssettlements` MODIFY `{$col}` {$type} NULL DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('scheduleseventssettlements')) {
            return;
        }
        foreach ($this->columns as $col => $type) {
            if (Schema::hasColumn('scheduleseventssettlements', $col)) {
                DB::statement("ALTER TABLE `scheduleseventssettlements` MODIFY `{$col}` {$type} NOT NULL");
            }
        }
    }
};
