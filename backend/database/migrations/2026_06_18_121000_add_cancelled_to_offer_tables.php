<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offer tables synced from CRM carry a `cancelled` flag, but it was being
     * dropped on import — so a cancelled camp/workshop/ticket stayed visible in
     * the app forever. Add the column so cancellations propagate and can be
     * filtered out when serving offers.
     */
    private array $tables = [
        'camps',
        'day_camps',
        'workshops_ygm',
        'workshops_european',
        'tickets',
        'courses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'cancelled')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->tinyInteger('cancelled')->default(0)->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'cancelled')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('cancelled');
            });
        }
    }
};
