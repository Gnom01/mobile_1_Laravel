<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PullUsersJob;
use App\Jobs\PullUsersRelationsJob;
use App\Models\Dictionary;
use App\Models\RelationLinkRequest;
use App\Services\CrmClient;
use App\Services\SerwerSmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsersRelationsController extends Controller
{
    /**
     * GET /api/users-relations/{parentGuid}
     *
     * Returns list of participants (children) related to the authenticated parent.
     */
    public function getRelatedUsers(Request $request, $parentGuid)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'error' => 'UNAUTHORIZED',
            ], 401);
        }

        $parent = DB::table('users')
            ->where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'error' => 'USER_NOT_FOUND',
            ], 404);
        }

        if ((int) $parent->UsersID !== (int) $authUser->UsersID) {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
            ], 403);
        }

        $relatedUsers = DB::table('usersrelations as ur')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.UsersID', '=', 'ur.UsersID')
                    ->where('u.Cancelled', '=', 0);
            })
            ->where('ur.Parent_UsersID', $authUser->UsersID)
            ->where('ur.Cancelled', 0)
            ->select(
                'u.fullName',
                'u.FirstName',
                'u.LastName',
                'u.DateOfBirdth',
                'u.Phone',
                'u.Email',
                'u.guid'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $relatedUsers,
        ]);
    }

    /**
     * POST /api/users-relations
     *
     * Creates a new participant (child account) linked to the authenticated parent.
     *
     * Body (JSON):
     *   firstName                       string  required
     *   lastName                        string  required
     *   dateOfBirth                     string  required  Y-m-d
     *   pesel                           string  optional
     *   genderDVID                      int     optional  (0 = unset)
     *   personalDataProcessingConsent   int     optional  0|1
     *   consentReceiveSmsEmailPhone     int     optional  0|1
     *   marketingAgreement              int     optional  0|1
     *   phone                           string  optional
     *   email                           string  optional
     */
    public function store(Request $request, CrmClient $crmClient)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'firstName'                      => ['required', 'string', 'max:100'],
            'lastName'                       => ['required', 'string', 'max:100'],
            'dateOfBirth'                    => ['required', 'date_format:Y-m-d'],
            'pesel'                          => ['sometimes', 'nullable', 'string', 'max:11'],
            'genderDVID'                     => ['sometimes', 'nullable', 'integer'],
            'personalDataProcessingConsent'  => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'consentReceiveSmsEmailPhone'    => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'marketingAgreement'             => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            'phone'                          => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'                          => ['sometimes', 'nullable', 'email', 'max:255'],
            // Typ relacji z perspektywy zalogowanego: 2=Rodzic, 1=Opiekun,
            // 3=Rodzeństwo, 5=Dziecko. Domyślnie 2 (dodaję swoje dziecko).
            'participantRelationsDVID'       => ['sometimes', 'nullable', 'integer', 'in:1,2,3,5'],
        ]);

        $crmPayload = [
            'usersID'                       => 0,
            'active'                        => '1',
            'dateOfBirdth'                  => $validated['dateOfBirth'],
            'postalCode'                    => '',
            'postPlace'                     => '',
            'memberCardNumber'              => '',
            'lastName'                      => $validated['lastName'],
            'firstName'                     => $validated['firstName'],
            'email'                         => $validated['email'] ?? '',
            'address'                       => '',
            'login'                         => $validated['email'] ?? '',
            'password'                      => '',
            'phone'                         => $validated['phone'] ?? '',
            'city'                          => '',
            'default_LocalizationsID'       => (string) ($authUser->Default_LocalizationsID ?? 0),
            'parent_UsersID'                => (int) $authUser->UsersID,
            'street'                        => '',
            'building'                      => '',
            'flat'                          => '',
            'description'                   => '',
            'genderDVID'                    => (int) ($validated['genderDVID'] ?? 0),
            'genderName'                    => '',
            'identityNumber'                => '',
            'pesel'                         => $validated['pesel'] ?? '',
            'entryFee'                      => 0,
            'activationDate'                => null,
            'paymentMethodsDVID'            => 0,
            'paymentMethodsName'            => '',
            'personalDataProcessingConsent' => (int) ($validated['personalDataProcessingConsent'] ?? 0),
            'consentReceiveSmsEmailPhone'   => (int) ($validated['consentReceiveSmsEmailPhone'] ?? 0),
            'marketingAgreement'            => (int) ($validated['marketingAgreement'] ?? 0),
            'fileName'                      => '',
            'fileExtension'                 => '',
            'positionsDVID'                 => 0,
            'employeesID'                   => 0,
            'statusUser'                    => 'Lead',
            'colorStatus'                   => '',
            'userStatus'                    => 3,
            'bankAccount'                   => '',
            'fileURL'                       => '',
            'cancelled'                     => '0',
            'birthPlace'                    => '',
            'voivodeshipDVID'               => '',
            'comunity'                      => '',
            'district'                      => '',
            'localizationsID'               => null,
            'localizationsIDArray'          => [(int) ($authUser->Default_LocalizationsID ?? 0)],
            'current_LocalizationsID'       => (string) ($authUser->Default_LocalizationsID ?? 0),
            // CRM utworzy relację rodzic–uczestnik na podstawie parent_UsersID
            // + tego pola (2 = zalogowany jest rodzicem dodawanej osoby).
            'participantRelationsDVID'      => (int) ($validated['participantRelationsDVID'] ?? 2),
        ];

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersForLocalization', $crmPayload);

            Log::info('UsersRelations store: CRM response', [
                'parent_id' => $authUser->UsersID,
                'status'    => $crmResp->status(),
            ]);

            if (!$crmResp->successful()) {
                throw new \Exception('CRM returned non-success status: ' . $crmResp->status());
            }
        } catch (\Exception $e) {
            Log::error('UsersRelations store: CRM call failed', [
                'parent_id' => $authUser->UsersID,
                'error'     => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zapisać uczestnika w systemie. Spróbuj ponownie.',
            ], 500);
        }

        // Sync updated users and relations back from CRM
        try {
            PullUsersJob::dispatchSync();
            PullUsersRelationsJob::dispatchSync();
        } catch (\Throwable $e) {
            Log::warning('UsersRelations store: sync jobs failed after CRM write', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Uczestnik został dodany.',
        ], 201);
    }

    // ─────────────────────────────────────────────
    // Flow "szukaj i powiąż istniejącą osobę" (weryfikacja kodem SMS):
    //   1. POST /users-relations/search        — wyszukanie osoby
    //   2. POST /users-relations/link/send-otp — SMS na numer Z BAZY (osoby lub jej opiekuna)
    //   3. POST /users-relations/link/verify   — kod → CRM setUsersRelation → sync
    // ─────────────────────────────────────────────

    /**
     * POST /api/users-relations/search
     *
     * Wyszukiwanie ISTNIEJĄCEJ osoby do powiązania. Wymaga pełnego imienia
     * i nazwiska (dopasowanie także po zamianie pól miejscami) albo numeru
     * telefonu — celowo bez wyszukiwania po fragmencie, żeby nie dało się
     * skanować bazy. Dane kontaktowe w wynikach są zamaskowane.
     */
    public function search(Request $request)
    {
        $authUser = $request->user();

        $request->validate([
            'firstName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lastName'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:25'],
        ]);

        $firstName = trim((string) $request->input('firstName', ''));
        $lastName  = trim((string) $request->input('lastName', ''));
        $phoneRaw  = trim((string) $request->input('phone', ''));

        $hasNames = $firstName !== '' && $lastName !== '';
        $hasPhone = $phoneRaw !== '';

        if (!$hasNames && !$hasPhone) {
            return response()->json([
                'success' => false,
                'error'   => 'SEARCH_CRITERIA_REQUIRED',
                'message' => 'Podaj imię i nazwisko albo numer telefonu.',
            ], 422);
        }

        $query = DB::table('users')
            ->where('Cancelled', 0)
            ->where('UsersID', '!=', (int) $authUser->UsersID);

        if ($hasNames) {
            $query->where(function ($q) use ($firstName, $lastName) {
                $q->where(function ($qq) use ($firstName, $lastName) {
                    $qq->where('FirstName', $firstName)->where('LastName', $lastName);
                })->orWhere(function ($qq) use ($firstName, $lastName) {
                    // użytkownik mógł wpisać nazwisko w polu imienia i odwrotnie
                    $qq->where('FirstName', $lastName)->where('LastName', $firstName);
                });
            });
        }

        if ($hasPhone) {
            $phone = $this->normalizePhone($phoneRaw);
            $query->where(function ($q) use ($phone) {
                $q->whereRaw('REPLACE(Phone, " ", "") = ?', [$phone])
                    ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['+48' . $phone])
                    ->orWhereRaw('REPLACE(Phone, " ", "") = ?', ['48' . $phone]);
            });
        }

        $matches = $query
            ->select('UsersID', 'guid', 'FirstName', 'LastName', 'DateOfBirdth', 'Phone')
            ->orderBy('LastName')
            ->orderBy('FirstName')
            ->limit(11)
            ->get();

        $truncated = $matches->count() > 10;
        $matches   = $matches->take(10);

        $results = [];
        foreach ($matches as $u) {
            $mode = $this->linkModeFor($u);

            $results[] = [
                'guid'                => $u->guid,
                'firstName'           => $u->FirstName,
                'lastName'            => $u->LastName,
                'birthYear'           => $u->DateOfBirdth ? (int) substr((string) $u->DateOfBirdth, 0, 4) : null,
                'alreadyLinked'       => $this->alreadyLinked((int) $authUser->UsersID, (int) $u->UsersID),
                'mode'                => $mode['mode'],
                'maskedPhone'         => $mode['mode'] === 'sms' ? $this->maskPhone($mode['recipient']->Phone) : null,
                'guardianFirstName'   => $mode['mode'] === 'guardian_sms' ? $mode['recipient']->FirstName : null,
                'guardianMaskedPhone' => $mode['mode'] === 'guardian_sms' ? $this->maskPhone($mode['recipient']->Phone) : null,
            ];
        }

        return response()->json([
            'success'   => true,
            'truncated' => $truncated,
            'results'   => $results,
        ]);
    }

    /**
     * POST /api/users-relations/link/send-otp
     *
     * Wysyła kod weryfikacyjny SMS dla powiązania z istniejącą osobą.
     * Kod idzie ZAWSZE na numer zapisany w bazie: osoby wyszukanej, a gdy
     * osoba ma już opiekuna (Opiekun prawny/Rodzice) — na numer opiekuna,
     * który przekazuje kod wnioskującemu (zgoda opiekuna). Osoba bez
     * telefonu i bez opiekuna z telefonem → tylko recepcja.
     */
    public function sendLinkOtp(Request $request, SerwerSmsClient $sms)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'guid'                     => ['required', 'string', 'max:36'],
            'participantRelationsDVID' => ['required', 'integer'],
        ]);

        $dvid = (int) $validated['participantRelationsDVID'];
        if (!in_array($dvid, $this->allowedRelationDvids(), true)) {
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_RELATION_TYPE',
                'message' => 'Nieprawidłowy typ relacji.',
            ], 422);
        }

        $target = DB::table('users')
            ->where('guid', $validated['guid'])
            ->where('Cancelled', 0)
            ->first();

        if (!$target) {
            return response()->json(['success' => false, 'error' => 'USER_NOT_FOUND'], 404);
        }

        if ((int) $target->UsersID === (int) $authUser->UsersID) {
            return response()->json(['success' => false, 'error' => 'CANNOT_LINK_SELF'], 422);
        }

        if ($this->alreadyLinked((int) $authUser->UsersID, (int) $target->UsersID)) {
            return response()->json([
                'success' => false,
                'error'   => 'ALREADY_LINKED',
                'message' => 'Osoby są już powiązane.',
            ], 409);
        }

        $mode = $this->linkModeFor($target);
        if ($mode['mode'] === 'reception') {
            return response()->json([
                'success' => false,
                'error'   => 'RECEPTION_REQUIRED',
                'message' => 'Tę osobę można powiązać wyłącznie w recepcji.',
            ], 422);
        }

        $recipient      = $mode['recipient'];
        $recipientPhone = $this->normalizePhone((string) $recipient->Phone);

        // Rate limit: max 3 kody na numer odbiorcy w 15 minut (jak w OtpController)
        $recentCount = RelationLinkRequest::where('otp_recipient_phone', $recipientPhone)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentCount >= 3) {
            return response()->json(['success' => false, 'error' => 'TOO_MANY_REQUESTS'], 429);
        }

        $code = (string) random_int(100000, 999999);

        $link = RelationLinkRequest::create([
            'requester_users_id'         => (int) $authUser->UsersID,
            'target_users_id'            => (int) $target->UsersID,
            'otp_recipient_users_id'     => (int) $recipient->UsersID,
            'otp_recipient_phone'        => $recipientPhone,
            'participant_relations_dvid' => $dvid,
            'code_hash'                  => bcrypt($code),
            // 10 min (nie 5): opiekun musi zdążyć przekazać kod wnioskującemu
            'expires_at'                 => now()->addMinutes(10),
            'attempts'                   => 0,
            'status'                     => 'pending',
        ]);

        $requesterName = trim(($authUser->FirstName ?? '') . ' ' . ($authUser->LastName ?? ''));
        $targetName    = trim((string) ($target->FirstName ?? ''));

        // Bez prefiksu <#>/app-hash — kod trafia na CUDZY telefon,
        // autouzupełnianie SMS Retriever nie ma tu zastosowania.
        $msg = $mode['mode'] === 'guardian_sms'
            ? "Kod powiazania: {$code}. {$requesterName} prosi o powiazanie konta z {$targetName} (Twoj podopieczny). Wazny 10 min."
            : "Kod powiazania: {$code}. {$requesterName} chce powiazac Twoje konto. Wazny 10 min.";

        $res = $sms->sendOtp($recipientPhone, $msg, (bool) config('services.sms.test_mode', false));

        $messageId = $res['data']['items'][0]['id'] ?? null;
        if ($messageId) {
            $link->update(['sent_message_id' => $messageId]);
        }

        Log::info('Relation link OTP sent', [
            'link_id'       => $link->id,
            'mode'          => $mode['mode'],
            'requester_id'  => $authUser->UsersID,
            'target_id'     => $target->UsersID,
            'phone_suffix'  => substr($recipientPhone, -3),
            'sms_ok'        => $res['ok'],
            'message_id'    => $messageId,
        ]);

        return response()->json([
            'success'           => true,
            'linkRequestId'     => $link->id,
            'mode'              => $mode['mode'],
            'maskedPhone'       => $this->maskPhone($recipient->Phone),
            'guardianFirstName' => $mode['mode'] === 'guardian_sms' ? $recipient->FirstName : null,
            'expiresInSeconds'  => 600,
        ]);
    }

    /**
     * POST /api/users-relations/link/verify
     *
     * Weryfikuje kod SMS i zapisuje powiązanie w CRM
     * (/CrmToMobileSync/setUsersRelation), po czym synchronizuje relacje
     * do lokalnej bazy. Kod jest unieważniany dopiero po sukcesie, żeby
     * przejściowy błąd CRM nie zmuszał do wysyłki nowego SMS-a.
     */
    public function verifyLink(Request $request, CrmClient $crmClient)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'linkRequestId' => ['required', 'integer'],
            'code'          => ['required', 'string', 'size:6'],
        ]);

        $link = RelationLinkRequest::where('id', (int) $validated['linkRequestId'])
            ->where('requester_users_id', (int) $authUser->UsersID) // anty-IDOR
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$link) {
            return response()->json(['success' => false, 'error' => 'INVALID_CODE'], 422);
        }

        if ($link->attempts >= 5) {
            return response()->json(['success' => false, 'error' => 'TOO_MANY_ATTEMPTS'], 429);
        }

        $link->increment('attempts');

        if (!Hash::check((string) $validated['code'], $link->code_hash)) {
            return response()->json(['success' => false, 'error' => 'INVALID_CODE'], 422);
        }

        $target = DB::table('users')
            ->where('UsersID', (int) $link->target_users_id)
            ->where('Cancelled', 0)
            ->first();

        if (!$target) {
            return response()->json(['success' => false, 'error' => 'USER_NOT_FOUND'], 404);
        }

        // Powiązanie mogło powstać w międzyczasie (np. w recepcji) — cel osiągnięty.
        if ($this->alreadyLinked((int) $authUser->UsersID, (int) $target->UsersID)) {
            $link->update(['status' => 'verified', 'expires_at' => now()]);
            return response()->json(['success' => true, 'message' => 'Osoby są już powiązane.']);
        }

        $crmPayload = [
            'parent_UsersID'           => (int) $authUser->UsersID,
            'usersID'                  => (int) $target->UsersID,
            'participantRelationsDVID' => (int) $link->participant_relations_dvid,
            'status'                   => 1,
            'default_LocalizationsID'  => (string) ($authUser->Default_LocalizationsID ?? 0),
            'current_LocalizationsID'  => (string) ($authUser->Default_LocalizationsID ?? 0),
        ];

        try {
            $crmResp = $crmClient->post('/CrmToMobileSync/setUsersRelation', $crmPayload);

            Log::info('Relation link: CRM response', [
                'link_id' => $link->id,
                'status'  => $crmResp->status(),
            ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // 409 = relacja istnieje już w CRM — traktujemy jak sukces.
            if ($e->response && $e->response->status() === 409) {
                $link->update(['status' => 'verified', 'expires_at' => now()]);

                try {
                    PullUsersRelationsJob::dispatchSync();
                } catch (\Throwable $syncEx) {
                    Log::warning('Relation link: sync job failed after CRM 409', [
                        'error' => $syncEx->getMessage(),
                    ]);
                }

                return response()->json(['success' => true, 'message' => 'Osoby są już powiązane.']);
            }

            Log::error('Relation link: CRM call failed', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zapisać powiązania. Spróbuj ponownie.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Relation link: CRM call failed', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zapisać powiązania. Spróbuj ponownie.',
            ], 500);
        }

        // Sukces — unieważnij prośbę (kod jednorazowy).
        $link->update(['status' => 'verified', 'expires_at' => now()]);

        try {
            PullUsersRelationsJob::dispatchSync();
        } catch (\Throwable $e) {
            Log::warning('Relation link: sync job failed after CRM write', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Osoba została powiązana.',
        ], 201);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Dozwolone typy relacji: ValueID ze słownika ParticipantRelations
     * (tabela dictionaries, sync z CRM), ograniczone do zakresu 1-5 —
     * mapowanie relacji odwrotnej w CRM (addUsersRelationsModels) obsługuje
     * tylko te wartości. Fallback 1-5, gdyby słownik nie był zsynchronizowany.
     */
    private function allowedRelationDvids(): array
    {
        $ids = Dictionary::where('DictionaryName', 'ParticipantRelations')
            ->where('Cancelled', 0)
            ->pluck('ValueID')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v >= 1 && $v <= 5)
            ->values()
            ->all();

        return $ids !== [] ? $ids : [1, 2, 3, 4, 5];
    }

    /**
     * Tryb weryfikacji dla osoby:
     *  - guardian_sms — osoba ma opiekuna (Opiekun prawny/Rodzice) z telefonem;
     *                   kod idzie do opiekuna (zgoda opiekuna),
     *  - sms          — osoba bez opiekuna, ma telefon; kod idzie do niej,
     *  - reception    — brak możliwości weryfikacji SMS → tylko recepcja.
     */
    private function linkModeFor(object $person): array
    {
        $guardians = DB::table('usersrelations as ur')
            ->join('users as g', function ($join) {
                $join->on('g.UsersID', '=', 'ur.Parent_UsersID')
                    ->where('g.Cancelled', '=', 0);
            })
            ->where('ur.UsersID', (int) $person->UsersID)
            ->where('ur.Cancelled', 0)
            // rola opiekuna wobec osoby: 1 = Opiekun prawny, 2 = Rodzice
            ->whereIn('ur.ParticipantRelationsDVID', [1, 2])
            ->orderBy('ur.UsersRelationsID')
            ->select('g.UsersID', 'g.FirstName', 'g.Phone')
            ->get();

        if ($guardians->isNotEmpty()) {
            $withPhone = $guardians->first(fn ($g) => trim((string) $g->Phone) !== '');

            return $withPhone
                ? ['mode' => 'guardian_sms', 'recipient' => $withPhone]
                : ['mode' => 'reception', 'recipient' => null];
        }

        if (trim((string) $person->Phone) !== '') {
            return ['mode' => 'sms', 'recipient' => $person];
        }

        return ['mode' => 'reception', 'recipient' => null];
    }

    /**
     * Czy między dwiema osobami istnieje już aktywna relacja (dowolny typ,
     * dowolny kierunek).
     */
    private function alreadyLinked(int $usersIdA, int $usersIdB): bool
    {
        return DB::table('usersrelations')
            ->where('Cancelled', 0)
            ->where(function ($q) use ($usersIdA, $usersIdB) {
                $q->where(function ($qq) use ($usersIdA, $usersIdB) {
                    $qq->where('Parent_UsersID', $usersIdA)->where('UsersID', $usersIdB);
                })->orWhere(function ($qq) use ($usersIdA, $usersIdB) {
                    $qq->where('Parent_UsersID', $usersIdB)->where('UsersID', $usersIdA);
                });
            })
            ->exists();
    }

    /**
     * Normalize phone number to 9-digits format (removes +48).
     * (Jak w OtpController — spójny format z tabelą otp_requests.)
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);

        if (str_starts_with($phone, '+48')) {
            return substr($phone, 3);
        }

        if (str_starts_with($phone, '48') && strlen($phone) === 11) {
            return substr($phone, 2);
        }

        return $phone;
    }

    /**
     * Maskuje numer telefonu do postaci "*** *** 456" (ostatnie 3 cyfry).
     */
    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return '*** *** ' . substr($digits, -3);
    }
}
