<?php

namespace App\Services;

use App\Models\CrmToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrmAuthService
{
    public function getAccessToken(): string
    {
        // lock: żeby 2 joby naraz nie robiły loginu
        return Cache::store('database')->lock('crm-token-lock', 10)->block(10, function () {

            $token = CrmToken::query()->latest('id')->first();

            // token ważny jeszcze > 60s => użyj
            if ($token && $token->expires_at && $token->expires_at->gt(now()->addSeconds(60))) {
                Log::info('CRM Auth: Using existing valid token. Expires at: ' . $token->expires_at);
                return $token->access_token;
            }

            // jeśli jest refresh_token => spróbuj refresh
            if ($token && $token->refresh_token) {
                Log::info('CRM Auth: Attempting token refresh.');
                $new = $this->refresh($token->refresh_token);
                if ($new) return $new;
            }

            // fallback => login
            Log::info('CRM Auth: Falling back to full login.');
            return $this->login();
        });
    }

    private function http()
    {
        return Http::baseUrl(config('services.crm.base_url'))
            ->timeout(20)
            ->retry(2, 300);
    }

    private function login(): string
    {
        Log::info('CRM Auth: Starting login attempt for ' . config('services.crm.username'));

        try {
            $resp = $this->http()
                ->post(config('services.crm.login_endpoint'), [
                    'email' => config('services.crm.username'),
                    'password' => config('services.crm.password'),
                ])
                ->throw()
                ->json();
            
            Log::info('CRM Auth: Login request successful.');
        } catch (\Throwable $e) {
            Log::error('CRM Auth: Login request failed: ' . $e->getMessage());
            throw $e;
        }

        // DOPASUJ te klucze do odpowiedzi Twojego CRM
        $access = $resp['access_token'] ?? $resp['token'] ?? null;
        $refresh = $resp['refresh_token'] ?? null;
        $expiresIn = (int)($resp['expires_in'] ?? 3600);

        if (!$access) {
            Log::error('CRM Auth: Login failed - missing access_token in response.', ['response' => $resp]);
            throw new \RuntimeException('CRM login: missing access_token/token');
        }

        Log::info('CRM Auth: Login successful, token stored. Expires in: ' . $expiresIn . 's');

        CrmToken::query()->delete(); // trzymamy 1 rekord
        CrmToken::create([
            'access_token' => $access,
            'refresh_token' => $refresh,
            'expires_at' => now()->addSeconds($expiresIn),
        ]);

        return $access;
    }

    private function refresh(string $refreshToken): ?string
    {
        try {
            $resp = $this->http()
                ->post(config('services.crm.refresh_endpoint'), [
                    'refresh_token' => $refreshToken,
                ])
                ->throw()
                ->json();

            $access = $resp['access_token'] ?? $resp['token'] ?? null;
            $refresh = $resp['refresh_token'] ?? $refreshToken;
            $expiresIn = (int)($resp['expires_in'] ?? 3600);

            if (!$access) {
                Log::warning('CRM Auth: Refresh failed - no access token in response.');
                return null;
            }

            Log::info('CRM Auth: Refresh successful.');

            CrmToken::query()->delete();
            CrmToken::create([
                'access_token' => $access,
                'refresh_token' => $refresh,
                'expires_at' => now()->addSeconds($expiresIn),
            ]);

            return $access;
        } catch (\Throwable $e) {
            Log::error('CRM Auth: Refresh exception: ' . $e->getMessage());
            return null;
        }
    }
}
