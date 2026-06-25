<?php

namespace App\Console\Commands;

use App\Services\CrmAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CrmOrderTest extends Command
{
    protected $signature = 'crm:order-test
                            {--guid=test-guid-001 : GUID for the test order}
                            {--users-id=1 : usersID to send}
                            {--loc-id= : current_LocalizationsID (auto-detects from DB user if omitted)}
                            {--fresh : Force fresh login (delete existing token first)}';

    protected $description = 'Test CRM login + token generation + POST to /Orders/createOrder';

    public function handle(CrmAuthService $auth): int
    {
        $portalBase = rtrim((string) config('services.crm.portal_base_url', config('services.crm.base_url', 'http://localhost/eds-web/API')), '/');
        $endpoint   = (string) config('services.crm.portal_orders_endpoint', '/Orders/createOrder');
        $fullUrl    = $portalBase . $endpoint;
        $authBase   = rtrim((string) config('services.crm.base_url', '?'), '/');
        $loginEp    = config('services.crm.login_endpoint', '/Users/login');
        $username   = config('services.crm.username', '(brak)');

        $this->line('');
        $this->info('=== CRM Order Test ===');
        $this->line("  Auth base URL  : {$authBase}");
        $this->line("  Login endpoint : {$loginEp}");
        $this->line("  Username       : {$username}");
        $this->line("  Portal URL     : {$portalBase}");
        $this->line("  Orders URL     : {$fullUrl}");
        $this->line("  Verify TLS     : " . (config('services.crm.verify_tls', true) ? 'yes' : 'no'));
        $this->line('');

        // 1. Wymuś świeże logowanie jeśli --fresh
        if ($this->option('fresh')) {
            $this->warn('  [--fresh] Usuwam stary token z bazy...');
            \App\Models\CrmToken::query()->delete();
        }

        // 2. Pobierz token (login / refresh / cache)
        $this->info('  Step 1: Pobieram token przez CrmAuthService...');
        try {
            $token = $auth->getAccessToken();
        } catch (\Throwable $e) {
            $this->error('  BŁĄD logowania: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line("  Token length   : " . strlen($token));
        $this->line("  Token preview  : " . substr($token, 0, 40) . '...');

        // Pokaż kiedy wygasa
        $crmToken = \App\Models\CrmToken::latest('id')->first();
        if ($crmToken && $crmToken->expires_at) {
            $this->line("  Expires at     : " . $crmToken->expires_at->toDateTimeString() . ' (UTC)');
            $this->line("  Wygasa za      : " . $crmToken->expires_at->diffForHumans());
        }

        $this->line('');

        // 3. Test call do /Orders/createOrder
        $guid    = $this->option('guid');
        $usersId = (int) $this->option('users-id');

        // Auto-detect localization from DB user if not specified
        if ($this->option('loc-id') !== null) {
            $locId = (int) $this->option('loc-id');
        } else {
            $dbUser = \App\Models\CrmUser::find($usersId);
            $locId  = (int) ($dbUser->Default_LocalizationsID ?? 0);
            $this->line("  Localization   : {$locId} (auto from user {$usersId} DB)");
        }

        $orderBody = [
            'guid'                    => $guid,
            'usersID'                 => $usersId,
            'payer_UsersID'           => $usersId,
            'productsID'              => 0,
            'coursesHeadingsID'       => 0,
            'current_LocalizationsID' => $locId,
            'contractAmount'          => 0,
            'contractPeriodFrom'      => now()->format('Y-m-d'),
            'dataTo'                  => now()->addMonth()->format('Y-m-d'),
            'paymentMethodsDVID'      => 5,
            'paymentMethodsP24'       => '',
            'allInstallments'         => [],
            'arrayOfSelectedInstallments' => '0',
            'paymentStatusesDVID'     => 1,
            'promotionsSalesIDList'   => '0',
            'entryFee'                => 0,
        ];

        $this->info("  Step 2: POST {$fullUrl}");
        $this->line('  Payload (guid): ' . $guid);

        // Try both wrapped {"type":"Order","body":{...}} and flat payload
        foreach (['wrapped' => true, 'flat' => false] as $label => $wrap) {
            $body = $wrap
                ? ['type' => 'Order', 'body' => $orderBody]
                : $orderBody;

            $this->line('');
            $this->info("  --- Format: {$label} ---");
            $startMs = (int) round(microtime(true) * 1000);

            try {
                $response = Http::timeout(30)
                    ->withToken($token)
                    ->when(!config('services.crm.verify_tls', true), fn ($h) => $h->withoutVerifying())
                    ->post($fullUrl, $body);

                $durationMs = (int) round(microtime(true) * 1000) - $startMs;
                $this->line("  HTTP status    : " . $response->status() . "  ({$durationMs}ms)");

                $parsed = $response->json();
                if (is_string($parsed)) {
                    // CRM sometimes returns double-encoded JSON with BOM
                    $parsed = json_decode(ltrim($parsed, "\xEF\xBB\xBF"), true);
                }
                $rawBody = $response->body();
                $this->line('  Raw body len   : ' . strlen($rawBody));
                $this->line(json_encode($parsed ?? $rawBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (\Throwable $e) {
                $this->error('  Wyjątek: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
