<?php

namespace App\Services\Order;

use App\Data\Order\CreateOrderData;
use App\Data\Order\OrderResult;
use App\Exceptions\Order\CrmIntegrationException;
use App\Exceptions\Order\CrmOrderException;
use App\Exceptions\Order\LocalSyncValidationException;
use App\Exceptions\Order\OrderAlreadyProcessingException;
use App\Exceptions\Order\OrderIdempotencyConflictException;
use App\Jobs\SyncOrderJob;
use App\Models\OrderRequest;
use App\Services\Order\CrmOrderPayloadBuilder;
use App\Services\Order\CrmCampOrderPayloadBuilder;
use App\Services\Order\CrmDayCampOrderPayloadBuilder;
use App\Services\Order\CrmTicketOrderPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderApplicationService
{
    /** @var CrmOrderClient */
    private $crmClient;

    /** @var OrderSyncService */
    private $syncService;

    public function __construct(CrmOrderClient $crmClient, OrderSyncService $syncService)
    {
        $this->crmClient = $crmClient;
        $this->syncService = $syncService;
    }

    /**
     * Create or idempotently return an order.
     *
     * Flow:
     *   1. Hash payload.
     *   2. Find-or-create OrderRequest by guid (inside short transaction).
     *   3. If already successful → return cached result.
     *   4. If processing and lock is fresh → throw.
     *   5. Mark processing, bump attempts, set locked_at.
     *   6. Call CRM (outside DB transaction).
     *   7. On CRM error → mark crm_failed, throw.
     *   8. On CRM success → persist response, mark crm_success.
     *   9. Run local sync.
     *  10. On sync ok  → mark local_synced.
     *  11. On sync fail → mark local_sync_failed, dispatch retry job.
     *  12. Return OrderResult.
     */
    public function createOrder(CreateOrderData $data): OrderResult
    {
        $payloadHash = hash('sha256', json_encode($data->payload));

        // ── Step 2: find-or-create inside short, locked transaction ───────────
        $orderRequest = DB::transaction(function () use ($data, $payloadHash): OrderRequest {
            /** @var OrderRequest $req */
            $req = OrderRequest::lockForUpdate()
                ->firstOrCreate(
                    ['guid' => $data->guid],
                    [
                        'user_id'       => $data->userId,
                        'payer_user_id' => $data->payerUserId,
                        'status'        => OrderRequest::STATUS_PENDING,
                        'payload_hash'  => $payloadHash,
                        'payload_json'  => $data->payload,
                    ],
                );

            // ── Step 8: idempotency conflict check ────────────────────────────
            if ($req->payload_hash !== $payloadHash) {
                throw new OrderIdempotencyConflictException($data->guid);
            }

            // ── Step 3: already done → return early flag ──────────────────────
            if ($req->isAlreadySuccessful()) {
                return $req;
            }

            // ── Step 4: processing lock ───────────────────────────────────────
            if ($req->isProcessingFresh()) {
                throw new OrderAlreadyProcessingException($data->guid);
            }

            // ── Step 5: claim processing slot ─────────────────────────────────
            $req->status    = OrderRequest::STATUS_PROCESSING;
            $req->attempts  = $req->attempts + 1;
            $req->locked_at = now();
            $req->save();

            return $req;
        });

        // ── Step 3 early-return path ──────────────────────────────────────────
        if ($orderRequest->isAlreadySuccessful()) {
            return OrderResult::fromOrderRequest($orderRequest, true);
        }

        // ── Step 6: call CRM — outside DB transaction ─────────────────────────
        try {
            $crmUser     = \App\Models\CrmUser::find($data->userId);
            $defaultLocId = (int) ($crmUser->Default_LocalizationsID ?? 0);
            $offerType = $orderRequest->payload_json['offerType'] ?? $orderRequest->payload_json['offer_type'] ?? null;
            if ($offerType === 'camp' || $offerType === 'summerCourse') {
                $crmBody = CrmCampOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } elseif ($offerType === 'dayCamp') {
                $crmBody = CrmDayCampOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } elseif ($offerType === 'ticket') {
                $crmBody = CrmTicketOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            } else {
                $crmBody = CrmOrderPayloadBuilder::build($orderRequest->payload_json, $data->guid, $data->userId, $defaultLocId, $data->participantUsersId);
            }

            // Zero-trust: bez poprawnego produktu/kursu zamówienie to śmieć
            // (cichy kontrakt na 0 zł). Odrzuć, zamiast tworzyć go w CRM.
            if ((int) ($crmBody['productsID'] ?? 0) <= 0 || (int) ($crmBody['coursesHeadingsID'] ?? 0) <= 0) {
                throw new CrmOrderException('Niekompletne dane oferty (brak produktu/kursu).', 422, ['invalid_offer']);
            }

            // Re-walidacja dostępności oferty tuż przed zapisem — odpowiednik
            // portalowej bramy websiteStatusesDVID∈{2,3} sprawdzanej przy każdym
            // wejściu. Dane lokalne są z sync co 5 min, więc to ostatnia linia
            // obrony przed zapisem na wycofaną/zamkniętą ofertę.
            $availabilityError = $this->validateOfferAvailability(
                (string) ($offerType ?? 'course'),
                (int) ($crmBody['coursesHeadingsID'] ?? 0),
                (int) ($crmBody['productsID'] ?? 0)
            );
            if ($availabilityError !== null) {
                throw new CrmOrderException($availabilityError, 422, ['offer_unavailable']);
            }

            // Portal blokuje zamówienie, gdy PŁATNIK jest małoletni (wiek z PESEL,
            // warunek ostry: > 18). Mobile nie miało żadnego odpowiednika.
            if ($offerType === null || $offerType === 'course') {
                $payerId = (int) ($crmBody['payer_UsersID'] ?? 0);
                if ($payerId > 0 && !$this->payerIsAdult($payerId)) {
                    throw new CrmOrderException(
                        'Płatnikiem umowy musi być osoba pełnoletnia.',
                        422,
                        ['payer_underage']
                    );
                }

                // Portalowy anty-duplikat (CheckUserForPurchaseKey) — chroni też
                // przed podwójnym kontraktem przy retry po crm_failed/timeout,
                // bo CRM createOrder nie jest idempotentny. null = brak
                // odpowiedzi CRM → nie blokujemy (ostatecznie decyduje CRM).
                $dupCheck = $this->crmClient->checkUserForPurchaseKey(
                    (string) ($crmBody['purchaseKey'] ?? ''),
                    (string) ($crmBody['contractPeriodFrom'] ?? now()->toDateString()),
                    (int) ($crmBody['usersID'] ?? $data->userId),
                    $data->guid
                );
                if ($dupCheck !== null && $dupCheck > 0) {
                    throw new CrmOrderException(
                        'Uczestnik jest już zapisany na ten kurs. Skontaktuj się z BOK.',
                        409,
                        ['duplicate_purchase']
                    );
                }
                if ($dupCheck === -1) {
                    throw new CrmOrderException(
                        'Tworzenie umowy jest w trakcie procesowania. Spróbuj ponownie za chwilę.',
                        409,
                        ['purchase_processing']
                    );
                }
            }

            $crmResponse = $this->crmClient->createOrder($crmBody, $data->guid);
        } catch (CrmOrderException | CrmIntegrationException $e) {
            // ── Step 7: CRM failure ───────────────────────────────────────────
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_CRM_FAILED,
                'error_message' => $e->getMessage(),
                'locked_at'     => null,
            ]);

            Log::warning('CRM order creation failed', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // ── Step 7.5: initiate online payment (non-fatal) ────────────────────────
        // CRM createOrder only creates documents; payment URL must be obtained by a
        // separate call to OnLineSchedulePayment with the newly created schedule IDs.
        $paymentToken = null;
        $paymentUrl   = null;

        if ((int) ($crmBody['paymentMethodsDVID'] ?? 0) === 5 && $crmResponse->contractsId > 0) {
            try {
                $localizationsId = (int) ($crmBody['current_LocalizationsID'] ?? 0);
                $usersId         = (int) ($crmBody['usersID'] ?? $data->userId);

                $schedules = $this->crmClient->fetchPaymentSchedules(
                    $crmResponse->contractsId,
                    $usersId,
                    $localizationsId,
                    $data->guid
                );

                // CRM uses typo key 'instalmets' (not 'installments')
                $scheduleIds = array_values(array_filter(
                    array_map(
                        fn ($s) => (int) ($s['usersPaymentsSchedulesID'] ?? 0),
                        $schedules['instalmets'] ?? []
                    )
                ));

                if (!empty($scheduleIds)) {
                    // Flutter wysyła returnUrl='' — operator ?? nie łapie pustego
                    // stringa, przez co fallback na config nigdy nie działał.
                    $returnUrl = trim((string) ($crmBody['returnUrl'] ?? ''));
                    if ($returnUrl === '') {
                        $returnUrl = (string) config('services.crm.mobile_checkout_return_url', '');
                    }
                    $paymentData = $this->crmClient->initiateOnlinePayment(
                        $scheduleIds,
                        $usersId,
                        $localizationsId,
                        5,
                        $returnUrl,
                        $data->guid
                    );

                    $paymentToken = $paymentData['token'] ?? $paymentData['html'] ?? null;
                    if ($paymentToken) {
                        // CRM zwraca gotowy URL płatności w polu 'token' — używamy go bezpośrednio
                        $paymentUrl = (string) $paymentToken;
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal: order was created; Flutter will receive payment_url=null
                // and should allow the user to retry payment from the order detail screen.
                Log::warning('Payment initiation after createOrder failed (non-fatal)', [
                    'guid'         => $data->guid,
                    'contracts_id' => $crmResponse->contractsId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        // ── Step 8: CRM success — persist response ────────────────────────────
        $orderRequest->update([
            'status'               => OrderRequest::STATUS_CRM_SUCCESS,
            'crm_response_json'    => $crmResponse->raw,
            'crm_contracts_id'     => $crmResponse->contractsId,
            'crm_users_products_id'=> $crmResponse->usersProductsId,
            'crm_payments_id'      => $crmResponse->paymentsId,
            'payment_token'        => $paymentToken,
            'payment_url'          => $paymentUrl,
            'locked_at'            => null,
        ]);

        // Celowane odświeżenie lokalnych tabel wg typu oferty — bez tego kupione
        // zapisy/bilety/miejsca pojawiały się w apce dopiero po pełnym crm:sync
        // (do ~5 min). Non-fatal.
        try {
            $offerTypeForPull = (string) ($orderRequest->payload_json['offerType']
                ?? $orderRequest->payload_json['offer_type'] ?? 'course');
            if ($offerTypeForPull === 'ticket') {
                \App\Jobs\PullUsersTicketsJob::dispatch();
            } elseif (in_array($offerTypeForPull, ['camp', 'summerCourse'], true)) {
                \App\Jobs\PullCampsJob::dispatch();
            } elseif ($offerTypeForPull === 'dayCamp') {
                \App\Jobs\PullDayCampsJob::dispatch();
            } else {
                \App\Jobs\PullUsersSchedulesJob::dispatch();
            }
        } catch (\Throwable $e) {
            Log::warning('Targeted pull dispatch after order failed (non-fatal)', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $offerType = $orderRequest->payload_json['offerType'] ?? $orderRequest->payload_json['offer_type'] ?? 'course';
            $title = "Zgłoszenie zarejestrowane";
            $body = "Dziękujemy za złożenie zamówienia!";
            
            if ($offerType === 'camp') {
                $title = "Zgłoszenie na obóz!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na obóz. Dziękujemy!";
            } elseif ($offerType === 'dayCamp') {
                $title = "Zgłoszenie na półkolonię!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na półkolonię. Dziękujemy!";
            } elseif ($offerType === 'workshop') {
                $title = "Zgłoszenie na warsztaty!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na warsztaty. Dziękujemy!";
            } elseif ($offerType === 'ticket') {
                $title = "Zakup biletu!";
                $body = "Twój bilet został wygenerowany pomyślnie.";
            } else {
                $title = "Zgłoszenie na kurs!";
                $body = "Zarejestrowaliśmy Twoje zgłoszenie na kurs. Dziękujemy!";
            }

            $recipientUserId = $data->payerUserId ?: $data->userId;
            
            app(\App\Services\FirebasePushService::class)->sendToUser(
                (int) $recipientUserId,
                $title,
                $body,
                'system'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send order push notification (non-fatal)', [
                'error' => $e->getMessage(),
                'guid'  => $data->guid,
            ]);
        }

        // ── Step 9: local sync ────────────────────────────────────────────────
        try {
            $this->syncService->syncFromCrmResponse($orderRequest);

            // ── Step 10: sync ok ──────────────────────────────────────────────
            $orderRequest->update([
                'status'       => OrderRequest::STATUS_LOCAL_SYNCED,
                'processed_at' => now(),
            ]);

            Log::info('Order created and synced', [
                'guid'             => $data->guid,
                'crm_contracts_id' => $orderRequest->crm_contracts_id,
            ]);
        } catch (LocalSyncValidationException $e) {
            // ── Step 11: sync fail — schedule retry ───────────────────────────
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Local sync failed after CRM success — dispatching retry', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            SyncOrderJob::dispatch($orderRequest->id)
                ->onQueue('orders')
                ->delay(now()->addSeconds(15));
        } catch (\Throwable $e) {
            $orderRequest->update([
                'status'        => OrderRequest::STATUS_LOCAL_SYNC_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Unexpected local sync error — dispatching retry', [
                'guid'  => $data->guid,
                'error' => $e->getMessage(),
            ]);

            SyncOrderJob::dispatch($orderRequest->id)
                ->onQueue('orders')
                ->delay(now()->addSeconds(15));
        }

        // ── Step 12 ───────────────────────────────────────────────────────────
        return OrderResult::fromOrderRequest($orderRequest->fresh(), false);
    }

    /**
     * Wiek płatnika z PESEL — jak portal (awaitContractConfirm): zamówienie
     * przechodzi tylko przy wieku > 18 (warunek ostry). Brak/niepoprawny PESEL
     * nie blokuje (część kont CRM nie ma PESEL) — wtedy decyduje CRM.
     */
    private function payerIsAdult(int $payerUsersId): bool
    {
        $pesel = (string) (DB::table('users')
            ->where('UsersID', $payerUsersId)
            ->value('Pesel') ?? '');
        $pesel = preg_replace('/\D/', '', $pesel);

        if (strlen($pesel) !== 11) {
            return true; // brak danych → nie blokujemy po stronie mobile
        }

        $year  = (int) substr($pesel, 0, 2);
        $month = (int) substr($pesel, 2, 2);
        $day   = (int) substr($pesel, 4, 2);

        if ($month >= 81) { $year += 1800; $month -= 80; }
        elseif ($month >= 61) { $year += 2200; $month -= 60; }
        elseif ($month >= 41) { $year += 2100; $month -= 40; }
        elseif ($month >= 21) { $year += 2000; $month -= 20; }
        else { $year += 1900; }

        if (!checkdate($month, $day, $year)) {
            return true;
        }

        $age = \Carbon\Carbon::createFromDate($year, $month, $day)->age;

        return $age > 18;
    }

    /**
     * Serwerowa re-walidacja dostępności oferty przed wysyłką do CRM —
     * odpowiednik portalowej bramy (websiteStatusesDVID ∈ {2,3}, cancelled=0,
     * daty i miejsca). Gdy oferty nie ma w lokalnej bazie (świeży rekord przed
     * syncem), przepuszczamy — CRM jest ostatecznym walidatorem.
     *
     * @return string|null komunikat błędu albo null gdy oferta sprzedawalna
     */
    private function validateOfferAvailability(string $offerType, int $coursesHeadingsId, int $productsId): ?string
    {
        $today = now()->toDateString();

        if (in_array($offerType, ['camp', 'summerCourse', 'dayCamp', 'ticket'], true)) {
            $table = match ($offerType) {
                'camp', 'summerCourse' => 'camps',
                'dayCamp'              => 'day_camps',
                'ticket'               => 'tickets',
            };

            $offer = DB::table($table)
                ->where(function ($q) use ($productsId, $coursesHeadingsId) {
                    $q->where('products_id', $productsId);
                    if ($coursesHeadingsId > 0) {
                        $q->orWhere('courses_headings_id', $coursesHeadingsId);
                    }
                })
                ->orderByDesc('id')
                ->first();

            if ($offer === null) {
                return null; // brak lokalnej kopii — decyduje CRM
            }

            if ((int) ($offer->website_status_id ?? 0) === 0 || (int) ($offer->cancelled ?? 0) === 1) {
                return 'Ta oferta nie jest już dostępna w sprzedaży.';
            }
            if ((int) ($offer->is_closed ?? 0) === 1 || (int) ($offer->available_places ?? 0) <= 0) {
                return 'Brak wolnych miejsc na tę ofertę.';
            }
            if (!empty($offer->ends_at) && substr((string) $offer->ends_at, 0, 10) < $today) {
                return 'Ta oferta już się zakończyła.';
            }
            if ($offerType === 'ticket') {
                if (!empty($offer->sale_starts_at) && (string) $offer->sale_starts_at > now()->toDateTimeString()) {
                    return 'Sprzedaż biletów jeszcze się nie rozpoczęła.';
                }
                if (!empty($offer->sale_ends_at) && (string) $offer->sale_ends_at < now()->toDateTimeString()) {
                    return 'Sprzedaż biletów została zakończona.';
                }
            }

            return null;
        }

        // Kurs regularny (i pozostałe typy oparte o coursesheadings).
        $course = DB::table('courses')
            ->where('coursesHeadingsID', $coursesHeadingsId)
            ->first(['websiteStatusesDVID', 'cancelled']);

        if ($course === null) {
            return null; // brak lokalnej kopii — decyduje CRM
        }

        if ((int) ($course->cancelled ?? 0) === 1) {
            return 'Ten kurs został wycofany ze sprzedaży.';
        }
        if (!in_array((int) ($course->websiteStatusesDVID ?? 0), [2, 3], true)) {
            return 'Zapisy na ten kurs są obecnie niedostępne.';
        }

        return null;
    }
}