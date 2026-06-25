<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolumny-listy/stringi w `usersproducts` były NOT NULL (default ''), ale silnik
 * sync (CrmSyncService::normalizeValue) zamienia puste stringi z CRM na NULL
 * przed zapisem → INSERT z NULL łamał ograniczenie NOT NULL i nowe wiersze
 * zamówień nie wchodziły do bazy mobilnej (rekord 647847/647849 ponawiany >50x).
 * Czynimy te kolumny nullable, zgodnie z istniejącym wzorcem „allow nullable
 * crm sync fields".
 */
return new class extends Migration
{
    // kolumna => typ (zachowany z migracji tworzącej)
    private array $columns = [
        'vatratesik'             => 'VARCHAR(10)',
        'paymentmethodsdvidlist' => 'VARCHAR(255)',
        'promotrionsidlist'      => 'VARCHAR(255)',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('usersproducts')) {
            return;
        }
        foreach ($this->columns as $col => $type) {
            if (Schema::hasColumn('usersproducts', $col)) {
                // doctrine/dbal nie jest wymagany — używamy surowego SQL (MySQL).
                DB::statement("ALTER TABLE `usersproducts` MODIFY `{$col}` {$type} NULL DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('usersproducts')) {
            return;
        }
        foreach ($this->columns as $col => $type) {
            if (Schema::hasColumn('usersproducts', $col)) {
                DB::statement("ALTER TABLE `usersproducts` MODIFY `{$col}` {$type} NOT NULL DEFAULT ''");
            }
        }
    }
};
