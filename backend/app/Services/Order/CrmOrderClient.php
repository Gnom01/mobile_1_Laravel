<?php

namespace App\Services\Order;

use App\Data\Order\CrmOrderResponse;
use App\Data\Order\CrmOrderSnapshot;
use App\Exceptions\Order\CrmIntegrationException;
use App\Exceptions\Order\CrmOrderException;
use App\Models\CrmApiLog;
use App\Services\CrmAuthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrmOrderClient
{
    private string $baseUrl;
    private string $ordersEndpoint;
    private CrmAuthService $auth;

    public function __construct(CrmAuthService $auth)
    {
        $this->auth           = $auth;
        $this->baseUrl        = rtrim((string) config('services.crm.portal_base_url', config('services.crm.base_url', 'http://localhost/eds-web/API')), '/');
        $this->ordersEndpoint = (string) config('services.crm.portal_orders_endpoint', '/Orders/createOrder');
    }

    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Create an order in CRM.
     * Retries 2 times on 502/503/504 and connection errors.
     *
     * @throws CrmOrderException       on 4xx (business error)
     * @throws CrmIntegrationException on 5xx / network failure
     */
    public function createOrder(array $crmBody, string $guid): CrmOrderResponse
    {
        $endpoint    = $this->ordersEndpoint;
        $safePayload = $this->sanitizePayload($crmBody);

        // CRM /Orders/createOrder expects a flat JSON body (no envelope wrapper)
        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $crmBody, $guid);

        // CRM may prepend PHP notices (HTML) before the JSON — extract the JSON part
        $parsed  = $this->extractCrmJson($rawBody);
        $crmStatus = (int) ($parsed['status'] ?? $httpStatus);
        $orderData = is_array($parsed['body'] ?? null) ? $parsed['body'] : ($parsed ?? []);

        $this->logRequest($guid, $endpoint, 'POST', $safePayload, $parsed, $httpStatus, $durationMs, $error);

        if ($error !== null) {
            throw new CrmIntegrationException("CRM connection error: {$error}", 0);
        }

        if ($httpStatus >= 500) {
            throw new CrmIntegrationException(
                "CRM server error {$httpStatus}",
                $httpStatus
            );
        }

        // CRM often returns HTTP 200 with status:4xx inside JSON body (business-level error)
        if ($crmStatus >= 400 && $crmStatus < 500) {
            throw new CrmOrderException(
                $this->extractErrorMessage($parsed, $crmStatus),
                $crmStatus,
                $parsed
            );
        }

        if ($crmStatus >= 500) {
            throw new CrmIntegrationException(
                "CRM server error {$crmStatus}",
                $crmStatus
            );
        }

        return CrmOrderResponse::fromArray($orderData);
    }

    /**
     * Fetch a full order snapshot from CRM by contractsID.
     * Used by SyncOrderJob for retry without re-creating.
     *
     * @throws CrmOrderException
     * @throws CrmIntegrationException
     */
    public function fetchOrderByContractId(int $contractsId, string $guid = ''): CrmOrderSnapshot
    {
        $endpoint = "/Orders/GetOrderSnapshot/{$contractsId}";

        [$body, $status, $durationMs, $error] = $this->executeWithRetry('GET', $endpoint, [], $guid);

        $safeBody = $this->sanitizeResponse($body);
        $this->logRequest($guid, $endpoint, 'GET', [], $safeBody, $status, $durationMs, $error);

        if ($error !== null) {
            throw new CrmIntegrationException("CRM connection error: {$error}", 0);
        }

        if ($status >= 400 && $status < 500) {
            throw new CrmOrderException(
                $this->extractErrorMessage($body, $status),
                $status
            );
        }

        if ($status >= 500) {
            throw new CrmIntegrationException("CRM server error {$status}", $status);
        }

        return CrmOrderSnapshot::fromArray($body);
    }

    // ─── Internal ──────────────────────────────────────────────────────────────

    /**
     * @return array{array, int, int, string|null}  [body, httpStatus, durationMs, errorMessage]
     */
    private function executeWithRetry(string $method, string $endpoint, array $payload, string $guid): array
    {
        $token     = $this->resolveToken();
        $url       = $this->baseUrl . $endpoint;
        $startedAt = hrtime(true);
        $error     = null;
        $status    = 0;
        $body      = [];

        try {
            $http = Http::timeout(30)->withToken($token);
            if (!config('services.crm.verify_tls', true)) {
                $http = $http->withoutVerifying();
            }
            $http = $http->retry(2, 1000, function (\Throwable $exception, $response): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    if ($response && in_array($response->status(), [502, 503, 504], true)) {
                        return true;
                    }
                    return false;
                }, false);

            switch (strtoupper($method)) {
                case 'POST':
                    $response = $http->post($url, $payload);
                    break;
                case 'GET':
                    $response = $http->get($url);
                    break;
                default:
                    $response = $http->send($method, $url, ['json' => $payload]);
                    break;
            }

            $status = $response->status();
            // Response may contain HTML (PHP notices) + JSON — store raw for parsing later
            $body   = ['__raw' => $response->body()];
        } catch (ConnectionException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return [$body, $status, $durationMs, $error];
    }

    /**
     * CRM sometimes prepends PHP notices (HTML) before the JSON response.
     * Extracts and decodes the JSON from the raw response body.
     */
    private function extractCrmJson(array $raw): array
    {
        $rawBody = $raw['__raw'] ?? '';
        if (!is_string($rawBody) || $rawBody === '') {
            return is_array($raw) && !isset($raw['__raw']) ? $raw : [];
        }

        // Strip UTF-8 BOM
        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF");

        // Find last '{' that starts the JSON object
        $jsonStart = strrpos($rawBody, '{"');
        if ($jsonStart !== false) {
            $jsonStr = substr($rawBody, $jsonStart);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function resolveToken(): string
    {
        // Delegates to CrmAuthService which handles: cache check → refresh → re-login
        return $this->auth->getAccessToken();
    }

    private function logRequest(
        string $guid,
        string $endpoint,
        string $method,
        array  $requestPayload,
        array  $responseBody,
        int    $httpStatus,
        int    $durationMs,
        ?string $error
    ): void {
        try {
            CrmApiLog::create([
                'guid'          => $guid ?: null,
                'endpoint'      => $endpoint,
                'method'        => $method,
                'request_json'  => $requestPayload,
                'response_json' => $this->sanitizeResponse($responseBody),
                'http_status'   => $httpStatus ?: null,
                'duration_ms'   => $durationMs,
                'error_message' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write CRM API log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove auth tokens and sensitive PII from outgoing request before logging.
     */
    private function sanitizePayload(array $payload): array
    {
        $redactKeys = ['password', 'token', 'Authorization', 'pesel', 'identityNumber'];
        array_walk_recursive($payload, static function (&$value, string $key) use ($redactKeys): void {
            if (in_array($key, $redactKeys, true)) {
                $value = '[REDACTED]';
            }
        });
        return $payload;
    }

    /**
     * Strip payment tokens from response before logging.
     */
    private function sanitizeResponse(array $response): array
    {
        $redactKeys = ['paymentToken', 'token', 'access_token', 'refresh_token'];
        array_walk_recursive($response, static function (&$value, string $key) use ($redactKeys): void {
            if (in_array($key, $redactKeys, true)) {
                $value = '[REDACTED]';
            }
        });
        return $response;
    }

    private function extractErrorMessage(array $body, int $status): string
    {
        return $body['message']
            ?? $body['error']
            ?? $body['title']
            ?? "CRM returned HTTP {$status}";
    }
}