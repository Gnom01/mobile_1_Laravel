<?php

namespace App\Services;

use App\Models\CrmToken;
use Illuminate\Support\Facades\Http;

class CrmClient
{
    public function __construct(private CrmAuthService $auth) {}

    private function httpClient(string $token)
    {
        return Http::baseUrl(config('services.crm.base_url'))
            ->withToken($token)
            ->withOptions([
                'verify' => (bool) config('services.crm.verify_tls', true),
            ])
            ->timeout(20);
    }

    public function get(string $url, array $query = [])
    {
        return $this->request('get', $url, $query);
    }

    public function post(string $url, array $data = [])
    
    {
        return $this->request('post', $url, $data);
    }

    public function put(string $url, array $data = [])
    {
        return $this->request('put', $url, $data);
    }

    public function delete(string $url, array $data = [])
    {
        return $this->request('delete', $url, $data);
    }

    private function request(string $method, string $url, array $payload)
    {
        $token = $this->auth->getAccessToken();

        $http = $this->httpClient($token);

        $fullUrl = config('services.crm.base_url') . $url;
        // \Illuminate\Support\Facades\Log::info("CrmClient: {$method} {$fullUrl}", ['payload' => $payload]);

        $resp = $http->{$method}($url, $payload);

        // Jeśli CRM zwróci 401 (token padł) => usuń token i ponów 1 raz
        if ($resp->status() === 401) {
            CrmToken::query()->delete();
            $token2 = $this->auth->getAccessToken();

            $resp = $this->httpClient($token2)->{$method}($url, $payload);
        }

        return $resp->throw();
    }
}
