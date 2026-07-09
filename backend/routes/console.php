<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Log::info('[CONSOLE.PHP] routes/console.php loaded - registering CRM schedules');

Schedule::useCache(config('crm_sync.lock_store', 'database'));

// scheduleseventssettlements ma WŁASNY rytm co 5 minut, niezależny od
// sekwencji poniżej (i jest z niej wyłączony — patrz CrmSync::handle).
// Powód: pojedynczy run full-synca trwa do ~15 min (max_execution_time 900s)
// i wpięty w sekwencję dyktowałby jej tempo wszystkim zasobom.
// runInBackground: nie blokuje pozostałych wpisów schedulera.
// withoutOverlapping(25): scheduler nie odpali drugiego procesu, a gdyby
// mimo to doszło do wyścigu, lock per-resource w CrmSyncService i tak
// kończy duplikat statusem "skipped" w ~0.01s (skip, nie kolejka).
// UWAGA na kolejność rejestracji: ten wpis MUSI być przed sekwencyjnym
// `crm:sync` — schedule:run wykonuje wpisy w kolejności rejestracji, a wpis
// pierwszoplanowy (bez runInBackground) blokuje kolejne w tym samym ticku.
// Zarejestrowany po sekwencji startowałby dopiero po jej zakończeniu.
Schedule::command('crm:sync PullSchedulesEventsSettlementsJob')
    ->everyFiveMinutes()
    ->withoutOverlapping(25)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/crm-sync-settlements.log'));

// Pojedynczy, SEKWENCYJNY przebieg synchronizacji co 5 minut.
// `crm:sync` (bez argumentu) uruchamia wszystkie joby Pull* po kolei, w
// kolejności zależności (Clients → Users → Payments → ...), w jednym procesie.
// Wcześniej rejestrowaliśmy 33 osobne wpisy `crm:sync {Job}`, które odpalały się
// JEDNOCZEŚNIE co 5 min — 33 równoległe połączenia do CRM/DB, brak respektowania
// kolejności (sieroty: płatności bez userów) i lock per-komenda nie ograniczał
// równoległości. Serializacja eliminuje thundering herd i porządkuje zależności.
// withoutOverlapping(30): jeśli przebieg trwa > 5 min, kolejny zostanie pominięty,
// a lock i tak wygaśnie po 30 min (zabezpieczenie przed zakleszczeniem).
Schedule::command('crm:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/crm-sync.log'))
    ->before(function () {
        Log::info('[SCHEDULER] crm:sync (all resources, sequential) is about to start');
    })
    ->after(function () {
        Log::info('[SCHEDULER] crm:sync (all resources, sequential) has finished');
    })
    ->onFailure(function () {
        Log::error('[SCHEDULER] crm:sync (all resources, sequential) failed');
    });

Schedule::call(function () {
    PushNotification::query()
        ->where('status', PushNotification::STATUS_SCHEDULED)
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->pluck('id')
        ->each(fn ($id) => SendPushNotificationJob::dispatch((int) $id));
})->everyMinute()->name('push-notifications-dispatch')->withoutOverlapping();

// Program wsparcia — naliczanie miesięcznych odnowień (wpłaty pending +
// zdarzenia billing_due dla przyszłej integracji operatora płatności).
// Uruchamiane tylko przy włączonej subskrypcji, żeby po wyłączeniu modułu
// nie naliczać pending istniejącym subskrybentom.
Schedule::command('support:process-renewals')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('services.support_program.enabled'))
    ->appendOutputTo(storage_path('logs/support-renewals.log'));
