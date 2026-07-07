<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmSyncClearLocks extends Command
{
    protected $signature = 'crm:sync-clear-locks';

    protected $description = 'Zwalnia osierocone locki sync:* po twardym ubiciu procesów syncu (deploy/restart kontenerów)';

    /**
     * Locki syncu żyją w cache_locks jako "<prefix>sync:<resource>" i są
     * zwalniane w finally — ale SIGKILL (deploy robi down/restart w trakcie
     * runa) pomija finally, a wiersz w bazie przeżywa restart kontenerów.
     * Zasób jest wtedy martwy aż do wygaśnięcia TTL (do 30 minut).
     *
     * Wywoływać WYŁĄCZNIE na starcie kontenera schedulera — w tym momencie
     * żaden prawowity właściciel locka nie żyje.
     */
    public function handle(): int
    {
        if (config('crm_sync.lock_store', 'database') !== 'database') {
            $this->info('Lock store is not "database" - nothing to clear.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('cache_locks')) {
            $this->info('Table cache_locks does not exist - nothing to clear.');
            return self::SUCCESS;
        }

        $deleted = DB::table('cache_locks')
            ->where('key', 'like', '%sync:%')
            ->delete();

        $this->info("Released {$deleted} orphaned sync lock(s).");

        return self::SUCCESS;
    }
}
