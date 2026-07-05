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
        // allowRetry=false: CRM createOrder is NOT idempotent (guid check uses usersBaskets
        // which is written at the END of the flow), so retries create duplicate contracts.
        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $crmBody, $guid, false);

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

    /**
     * Fetch payment schedules for an existing contract.
     * Called after createOrder to get the usersPaymentsSchedulesID list needed
     * to initiate the online payment step.
     */
    public function fetchPaymentSchedules(
        int    $contractsId,
        int    $usersId,
        int    $localizationsId,
        string $guid
    ): ?array {
        $endpoint = '/Orders/CalculateProductForUser';
        $payload  = [
            'keyController'           => 'paymentByContractsID',
            'contractsID'             => $contractsId,
            'usersID'                 => $usersId,
            'current_LocalizationsID' => $localizationsId,
        ];

        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $payload, $guid, true);
        $body = $this->parseCrmBodyResponse($rawBody['__raw'] ?? '');
        $this->logRequest($guid, $endpoint, 'POST', $payload, $this->sanitizeResponse($body), $httpStatus, $durationMs, $error);

        if ($error !== null || $httpStatus >= 400) {
            Log::warning('CRM fetchPaymentSchedules failed', [
                'contractsId' => $contractsId,
                'error'       => $error,
                'httpStatus'  => $httpStatus,
            ]);
            return null;
        }

        $crmStatus = (int) ($body['status'] ?? 200);
        if ($crmStatus >= 400) {
            Log::warning('CRM fetchPaymentSchedules business error', [
                'crmStatus' => $crmStatus,
                'message'   => $body['message'] ?? '',
            ]);
            return null;
        }

        return $body;
    }

    /**
     * Initiate online payment for given schedule IDs (P24 / PayNow).
     * CRM calls OnLineSchedulePayment which registers the transaction and returns a token.
     *
     * $scheduleIds — usersPaymentsSchedulesID values from fetchPaymentSchedules
     * Returns ['token' => '...', 'sessionID' => '...', 'html' => '...'].
     *
     * @throws CrmOrderException       błąd biznesowy CRM (status 4xx w kopercie)
     * @throws CrmIntegrationException błąd połączenia / HTTP
     */
    public function initiateOnlinePayment(
        array  $scheduleIds,
        int    $usersId,
        int    $localizationsId,
        int    $paymentMethodsDvid,
        string $returnUrl,
        string $guid
    ): array {
        // CRM OnLineSchedulePayment parses "x/usersID/scheduleID" per entry, comma-separated
        $schedulesParam = implode(',', array_map(
            fn (int $id) => "0/{$usersId}/{$id}",
            $scheduleIds
        ));

        return $this->sendOnlineSchedulePayment($schedulesParam, $usersId, $localizationsId, $paymentMethodsDvid, $returnUrl, $guid);
    }

    /**
     * Wariant initiateOnlinePayment dla ekranu „Opłać" (raty): jedna płatność
     * może obejmować raty różnych uczestników (dzieci), więc każda pozycja
     * niesie własne usersID, a top-level usersID to płatnik (rodzic).
     *
     * Format 1:1 jak w portalu (to_pay.js): pozycje "płatnik/uczestnik/rata",
     * current_LocalizationsID = -1.
     *
     * $entries — tablice ['usersID' => .., 'scheduleID' => ..]
     *
     * @throws CrmOrderException       błąd biznesowy CRM (status 4xx w kopercie)
     * @throws CrmIntegrationException błąd połączenia / HTTP
     */
    public function initiateSchedulePayment(
        array  $entries,
        int    $payerUsersId,
        int    $paymentMethodsDvid,
        string $returnUrl,
        string $guid,
        string $buyerNip = ''
    ): array {
        $schedulesParam = implode(',', array_map(
            fn (array $e) => $payerUsersId . '/' . ((int) $e['usersID']) . '/' . ((int) $e['scheduleID']),
            $entries
        ));

        return $this->sendOnlineSchedulePayment($schedulesParam, $payerUsersId, -1, $paymentMethodsDvid, $returnUrl, $guid, $buyerNip);
    }

    private function sendOnlineSchedulePayment(
        string $schedulesParam,
        int    $usersId,
        int    $localizationsId,
        int    $paymentMethodsDvid,
        string $returnUrl,
        string $guid,
        string $buyerNip = ''
    ): array {
        $endpoint = '/Orders/CalculateProductForUser';

        $payload = [
            'keyController'            => 'onLineSchedulePayment',
            'usersPaymentsSchedulesID' => $schedulesParam,
            'usersID'                  => $usersId,
            'current_LocalizationsID'  => $localizationsId,
            'paymentMethodsDVID'       => $paymentMethodsDvid,
            'urlLink'                  => $returnUrl,
            'NIP'                      => $buyerNip,
        ];

        // allowRetry=false: creating a payment is not idempotent
        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $payload, $guid, false);
        $body = $this->parseCrmBodyResponse($rawBody['__raw'] ?? '');
        $this->logRequest($guid, $endpoint, 'POST', $payload, $this->sanitizeResponse($body), $httpStatus, $durationMs, $error);

        if ($error !== null || $httpStatus >= 400) {
            Log::warning('CRM initiateOnlinePayment failed', [
                'guid'       => $guid,
                'error'      => $error,
                'httpStatus' => $httpStatus,
            ]);
            throw new CrmIntegrationException(
                $error !== null ? "CRM connection error: {$error}" : "CRM HTTP {$httpStatus}",
                $httpStatus
            );
        }

        $crmStatus = (int) ($body['status'] ?? 200);
        if ($crmStatus >= 400) {
            Log::warning('CRM initiateOnlinePayment business error', [
                'guid'      => $guid,
                'crmStatus' => $crmStatus,
                'message'   => $body['message'] ?? '',
            ]);
            throw new CrmOrderException($this->extractErrorMessage($body, $crmStatus), $crmStatus, $body);
        }

        return $body;
    }

    /**
     * Calculate or set workshop pricing/selection in CRM via CalculateProductForUser.
     *
     * CRM zwraca błędy biznesowe (409 rozjazd cen, 400 brak cennika) jako
     * HTTP 200 ze `status` w kopercie JSON — wcześniej były połykane i flow
     * szedł do płatności bez koszyka w CRM.
     *
     * @throws CrmOrderException       błąd biznesowy CRM (status 4xx w kopercie)
     * @throws \Exception              błąd połączenia / HTTP
     */
    public function calculateWorkshopPricing(array $payload, string $guid = ''): array
    {
        $endpoint = '/Orders/CalculateProductForUser';
        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $payload, $guid, true);
        $envelope = $this->extractCrmJson($rawBody);
        $this->logRequest($guid, $endpoint, 'POST', $payload, $this->sanitizeResponse($envelope), $httpStatus, $durationMs, $error);

        if ($error !== null || $httpStatus >= 400) {
            Log::warning('CRM calculateWorkshopPricing failed', [
                'guid'       => $guid,
                'error'      => $error,
                'httpStatus' => $httpStatus,
            ]);
            throw new \Exception("Błąd połączenia z CRM przy kalkulacji ceny: {$error}");
        }

        $crmStatus = (int) ($envelope['status'] ?? ($httpStatus ?: 200));
        if ($crmStatus >= 400) {
            throw new CrmOrderException(
                $this->extractErrorMessage($envelope, $crmStatus),
                $crmStatus,
                $envelope
            );
        }

        return is_array($envelope['body'] ?? null) ? $envelope['body'] : $envelope;
    }

    /**
     * Portalowy anty-duplikat zapisu na kurs: CheckUserForPurchaseKey.
     * Zwraca: 0 = wolne, >0 = uczestnik już zapisany, -1 = umowa w procesowaniu,
     * null = nie udało się sprawdzić (traktować nie-blokująco).
     */
    public function checkUserForPurchaseKey(
        string $purchaseKey,
        string $dateFrom,
        int    $usersId,
        string $guid = ''
    ): ?int {
        if ($purchaseKey === '') {
            return null;
        }

        $endpoint = '/Orders/CalculateProductForUser';
        $payload  = [
            'keyController'           => 'CheckUserForPurchaseKey',
            'purchaseKey'             => $purchaseKey,
            'dateFrom'                => $dateFrom,
            'usersID'                 => $usersId,
            'current_LocalizationsID' => '0',
        ];

        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $payload, $guid, true);
        $envelope = $this->extractCrmJson($rawBody);
        $this->logRequest($guid, $endpoint, 'POST', $payload, $this->sanitizeResponse($envelope), $httpStatus, $durationMs, $error);

        if ($error !== null || $httpStatus >= 400) {
            return null;
        }

        $body = $envelope['body'] ?? null;
        if (is_array($body)) {
            $body = $body['result'] ?? $body[0] ?? null;
        }
        if ($body === null || !is_numeric($body)) {
            return null;
        }

        return (int) $body;
    }

    /**
     * Krok płatności warsztatów — portal wysyła OrderPayment przez
     * POST /OrdersPay/payPortal z płaskim body. Wcześniej mobile wysyłał
     * kopertę {type:'OrderPayment'} na /Orders/createOrder, którego CRM
     * dla tej koperty nie obsługuje.
     *
     * @return array pełna koperta CRM (status, message, token/html/…)
     * @throws CrmOrderException|CrmIntegrationException
     */
    public function payPortal(string $guid, string $returnUrl, string $paymentMethodsP24 = '5'): array
    {
        $endpoint = '/OrdersPay/payPortal';
        $payload  = [
            'guid'                    => $guid,
            'paymentMethodsP24'       => $paymentMethodsP24,
            'returnUrl'               => $returnUrl,
            'current_LocalizationsID' => -1,
        ];

        // allowRetry=false: inicjacja płatności nie jest idempotentna.
        [$rawBody, $httpStatus, $durationMs, $error] = $this->executeWithRetry('POST', $endpoint, $payload, $guid, false);
        $envelope = $this->extractCrmJson($rawBody);
        $this->logRequest($guid, $endpoint, 'POST', $payload, $this->sanitizeResponse($envelope), $httpStatus, $durationMs, $error);

        if ($error !== null) {
            throw new CrmIntegrationException("CRM connection error: {$error}", 0);
        }

        if ($httpStatus >= 500) {
            throw new CrmIntegrationException("CRM server error {$httpStatus}", $httpStatus);
        }

        $crmStatus = (int) ($envelope['status'] ?? ($httpStatus ?: 200));
        if ($crmStatus >= 400) {
            throw new CrmOrderException(
                $this->extractErrorMessage($envelope, $crmStatus),
                $crmStatus,
                $envelope
            );
        }

        return $envelope;
    }

    // ─── Internal ──────────────────────────────────────────────────────────────

    /**
     * @param  bool  $allowRetry  Set to false for non-idempotent POST calls (createOrder)
     *                            to avoid duplicate records when CRM is slow.
     * @return array{array, int, int, string|null}  [body, httpStatus, durationMs, errorMessage]
     */
    private function executeWithRetry(string $method, string $endpoint, array $payload, string $guid, bool $allowRetry = true): array
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
            if ($allowRetry) {
                $http = $http->retry(2, 1000, function (\Throwable $exception, $response): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    if ($response && in_array($response->status(), [502, 503, 504], true)) {
                        return true;
                    }
                    return false;
                }, false);
            }

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
            $rawResponseBody = $response->body();
            $body   = ['__raw' => $rawResponseBody];

            Log::debug('[CRM RAW] żądanie do CRM', [
                'guid'     => $guid,
                'method'   => $method,
                'url'      => $url,
                'payload'  => $payload,
            ]);
            Log::debug('[CRM RAW] odpowiedź z CRM', [
                'guid'       => $guid,
                'url'        => $url,
                'httpStatus' => $status,
                'body'       => $rawResponseBody,
            ]);
        } catch (ConnectionException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return [$body, $status, $durationMs, $error];
    }

    /**
     * Parse CRM response body using first-occurrence of '{'.
     * Unlike extractCrmJson (which uses strrpos and is safe for simple flat responses),
     * this method correctly handles nested arrays like instalmets/contracts.
     * Returns the inner 'body' if the CRM envelope is detected, otherwise the decoded object.
     */
    private function parseCrmBodyResponse(string $rawBody): array
    {
        if ($rawBody === '') {
            return [];
        }
        $rawBody    = ltrim($rawBody, "\xEF\xBB\xBF");
        $firstBrace = strpos($rawBody, '{');
        if ($firstBrace === false) {
            return [];
        }
        $decoded = json_decode(substr($rawBody, $firstBrace), true);
        if (!is_array($decoded)) {
            return [];
        }
        // CRM envelope: { status, message, token, body: {...} }
        if (isset($decoded['body']) && is_array($decoded['body'])) {
            return $decoded['body'];
        }
        return $decoded;
    }

    /**
     * CRM sometimes prepends PHP notices (HTML) before the JSON response.
     * Extracts and decodes the JSON from the raw response body.
     * Returns the full CRM envelope (status, message, token, body, …).
     */
    private function extractCrmJson(array $raw): array
    {
        $rawBody = $raw['__raw'] ?? '';
        if (!is_string($rawBody) || $rawBody === '') {
            return is_array($raw) && !isset($raw['__raw']) ? $raw : [];
        }

        // Strip UTF-8 BOM
        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF");

        // Find the first '{' — start of the CRM JSON envelope (skips any leading PHP notices/HTML)
        $jsonStart = strpos($rawBody, '{');
        if ($jsonStart !== false) {
            $decoded = json_decode(substr($rawBody, $jsonStart), true);
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