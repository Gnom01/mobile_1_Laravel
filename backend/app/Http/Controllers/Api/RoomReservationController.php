<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RoomUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\RoomReservation;
use App\Services\CrmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rezerwacja sal tanecznych przez instruktora (Etap B: miasta + dostępność).
 *
 * Zajętość sal czytana jest NA ŻYWO z CRM (SchedulesEventsSettlements przez
 * /CrmToMobileSync/getRoomOccupancyMobile) — celowo poza 5-minutowym syncem,
 * bo ekran dostępności odświeża się co minutę. Sale i lokalizacje pochodzą
 * z lokalnych mirrorów (classrooms, localizations).
 */
class RoomReservationController extends Controller
{
    /**
     * GET /api/instructor/room-reservations/cities
     *
     * Miasta i lokalizacje, w których można rezerwować sale
     * (lokalizacje aktywne, mające przynajmniej jedną salę).
     */
    public function cities(Request $request): JsonResponse
    {
        if (empty($this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $localizations = DB::table('localizations as l')
            ->join('classrooms as c', function ($join) {
                $join->on('c.localizationsID', '=', 'l.LocalizationsID')
                    ->where('c.cancelled', '=', 0);
            })
            ->where('l.Cancelled', 0)
            ->where('l.Hidden', 0)
            ->groupBy('l.LocalizationsID', 'l.LocalizationName', 'l.City')
            ->orderBy('l.City')
            ->orderBy('l.LocalizationName')
            ->select('l.LocalizationsID', 'l.LocalizationName', 'l.City', DB::raw('COUNT(c.classRoomsID) as roomCount'))
            ->get();

        $cities = $localizations
            ->groupBy(fn ($l) => trim((string) $l->City) !== '' ? trim((string) $l->City) : 'Inne')
            ->map(fn ($group, $city) => [
                'city' => $city,
                'localizations' => $group->map(fn ($l) => [
                    'localizationsID' => (int) $l->LocalizationsID,
                    'localizationName' => $l->LocalizationName,
                    'roomCount' => (int) $l->roomCount,
                ])->values(),
            ])
            ->values();

        return response()->json(['success' => true, 'cities' => $cities]);
    }

    /**
     * GET /api/instructor/room-reservations/availability
     *
     * Parametry: date (Y-m-d, wymagane) + localizationsID albo city.
     * Zwraca sale z zajętymi przedziałami i powodem zajętości.
     */
    public function availability(Request $request, CrmClient $crmClient): JsonResponse
    {
        if (empty($this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
            'date'            => ['required', 'date_format:Y-m-d'],
            'localizationsID' => ['sometimes', 'nullable', 'integer'],
            'city'            => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $date = $validated['date'];

        $localizationsQuery = DB::table('localizations')
            ->where('Cancelled', 0)
            ->where('Hidden', 0);

        if (!empty($validated['localizationsID'])) {
            $localizationsQuery->where('LocalizationsID', (int) $validated['localizationsID']);
        } elseif (!empty($validated['city'])) {
            $localizationsQuery->where('City', trim($validated['city']));
        } else {
            return response()->json([
                'success' => false,
                'error'   => 'LOCATION_REQUIRED',
                'message' => 'Podaj lokalizację albo miasto.',
            ], 422);
        }

        $localizations = $localizationsQuery
            ->orderBy('LocalizationName')
            ->get(['LocalizationsID', 'LocalizationName', 'City']);

        if ($localizations->isEmpty()) {
            return response()->json(['success' => true, 'date' => $date, 'localizations' => []]);
        }

        $result = [];
        foreach ($localizations as $loc) {
            $rooms = DB::table('classrooms')
                ->where('localizationsID', (int) $loc->LocalizationsID)
                ->where('cancelled', 0)
                ->orderBy('orderPosition')
                ->orderBy('classRoomName')
                ->get(['classRoomsID', 'classRoomName']);

            if ($rooms->isEmpty()) {
                continue;
            }

            // Zajętość na żywo z CRM (świeżość < 1 min jest wymaganiem funkcji).
            try {
                $resp = $crmClient->get('/CrmToMobileSync/getRoomOccupancyMobile', [
                    'localizationsID'         => (int) $loc->LocalizationsID,
                    'dateFrom'                => $date,
                    'dateTo'                  => $date,
                    'current_LocalizationsID' => '0',
                ]);
                $occupancy = collect($resp->json('body') ?: []);
            } catch (\Exception $e) {
                Log::error('Room availability: CRM occupancy fetch failed', [
                    'localizationsID' => $loc->LocalizationsID,
                    'error'           => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Nie udało się pobrać zajętości sal. Spróbuj ponownie.',
                ], 503);
            }

            $byRoom = $occupancy->groupBy(fn ($row) => (int) ($row['classRoomsID'] ?? 0));

            // Nałóż lokalne holdy (tymczasowe rezerwacje) na zajętość z CRM,
            // żeby dwolny slot trzymany przez innego instruktora był widoczny.
            $holdsByRoom = $this->activeHoldsByRoom((int) $loc->LocalizationsID, $date);

            $result[] = [
                'localizationsID'  => (int) $loc->LocalizationsID,
                'localizationName' => $loc->LocalizationName,
                'city'             => $loc->City,
                'rooms'            => $rooms->map(function ($room) use ($byRoom, $holdsByRoom) {
                    $roomId = (int) $room->classRoomsID;
                    $crmBusy = ($byRoom->get($roomId) ?? collect())
                        ->map(fn ($row) => $this->presentBusySlot((array) $row));
                    $holds = $holdsByRoom->get($roomId) ?? collect();

                    return [
                        'classRoomsID'  => $roomId,
                        'classRoomName' => $room->classRoomName,
                        'busy'          => $crmBusy->concat($holds)->values(),
                    ];
                })->values(),
            ];
        }

        return response()->json([
            'success'       => true,
            'date'          => $date,
            'localizations' => $result,
        ]);
    }

    /**
     * GET /api/instructor/room-reservations/participants
     *
     * Wyszukiwarka osób do rezerwacji: po imieniu, nazwisku, telefonie
     * lub e-mailu. Opcjonalnie zawężone do miasta.
     */
    public function participants(Request $request): JsonResponse
    {
        if (empty($this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'city'   => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $search = trim((string) $request->input('search'));
        $city   = trim((string) $request->input('city', ''));

        $query = DB::table('users')
            ->where('Cancelled', 0)
            ->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('FirstName', 'like', $like)
                    ->orWhere('LastName', 'like', $like)
                    ->orWhere('fullName', 'like', $like)
                    ->orWhereRaw('REPLACE(Phone, " ", "") like ?', ['%' . str_replace(' ', '', $search) . '%'])
                    ->orWhere('Email', 'like', $like);
            });

        if ($city !== '') {
            // Osoba przypisana do miasta przez swoją domyślną lokalizację.
            $query->whereIn('Default_LocalizationsID', function ($sub) use ($city) {
                $sub->select('LocalizationsID')->from('localizations')->where('City', $city);
            });
        }

        $people = $query
            ->orderBy('LastName')->orderBy('FirstName')
            ->limit(30)
            ->get(['UsersID', 'guid', 'FirstName', 'LastName', 'Phone', 'Email', 'DateOfBirdth']);

        return response()->json([
            'success' => true,
            'participants' => $people->map(fn ($u) => [
                'usersID'    => (int) $u->UsersID,
                'guid'       => $u->guid,
                'firstName'  => $u->FirstName,
                'lastName'   => $u->LastName,
                'maskedPhone' => $this->maskPhone($u->Phone),
                'category'   => $this->ageCategory($u->DateOfBirdth),
                'birthYear'  => $u->DateOfBirdth ? (int) substr((string) $u->DateOfBirdth, 0, 4) : null,
            ])->values(),
        ]);
    }

    /**
     * GET /api/instructor/room-reservations/products
     *
     * Cenniki zajęć indywidualnych (CRM products/getIndividualClassesProducts,
     * ProductsLevel2DVID=6). Instruktor wybiera jeden cennik dla wszystkich
     * uczestników.
     */
    public function products(Request $request, CrmClient $crmClient): JsonResponse
    {
        if (empty($this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
            'localizationsID' => ['required', 'integer'],
        ]);

        try {
            $resp = $crmClient->post('/products/getIndividualClassesProducts', [
                'productsLevel2DVID'      => 6, // zajęcia indywidualne
                'localizationsIDList'     => [(int) $validated['localizationsID']],
                'current_LocalizationsID' => (string) $validated['localizationsID'],
            ]);
            $rows = collect($resp->json('body') ?: []);
        } catch (\Exception $e) {
            Log::error('Room reservation products: CRM fetch failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się pobrać cenników. Spróbuj ponownie.',
            ], 503);
        }

        $products = $rows->map(fn ($r) => [
            'productsID'   => (int) ($r['productsID'] ?? 0),
            'productName'  => (string) ($r['productName'] ?? ''),
            'price'        => (float) ($r['price'] ?? 0),
            'numberOfLessons' => (int) ($r['numberOfLessons'] ?? 0),
            'classTypeName' => (string) ($r['classTypeName'] ?? ''),
            'instructorName' => trim((string) ($r['instructorsName'] ?? '')),
        ])->filter(fn ($p) => $p['productsID'] > 0)->unique('productsID')->values();

        return response()->json(['success' => true, 'products' => $products]);
    }

    /**
     * POST /api/instructor/room-reservations
     *
     * Tworzy tymczasową rezerwację sali (hold 15 min) z ponowną walidacją
     * dostępności w transakcji (blokada wyścigów). Naliczenia/płatności
     * dojdą w kolejnym etapie — na razie hold utrzymuje slot i wygasa.
     */
    public function store(Request $request): JsonResponse
    {
        if (empty($employeeIds = $this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
            'localizationsID' => ['required', 'integer'],
            'classRoomsID'    => ['required', 'integer'],
            'date'            => ['required', 'date_format:Y-m-d'],
            'timeFrom'        => ['required', 'date_format:H:i'],
            'timeTo'          => ['required', 'date_format:H:i'],
            'mode'            => ['required', 'in:exclusive,shared'],
            'participantIDs'  => ['required', 'array', 'min:1'],
            'participantIDs.*' => ['integer'],
            'productID'       => ['sometimes', 'nullable', 'integer'],
        ]);

        if ($validated['timeTo'] <= $validated['timeFrom']) {
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_TIME_RANGE',
                'message' => 'Godzina zakończenia musi być późniejsza niż rozpoczęcia.',
            ], 422);
        }

        $authUserId = (int) $request->user()->getKey();

        // Sala musi istnieć w wybranej lokalizacji.
        $room = DB::table('classrooms')
            ->where('classRoomsID', (int) $validated['classRoomsID'])
            ->where('localizationsID', (int) $validated['localizationsID'])
            ->where('cancelled', 0)
            ->first();

        if (!$room) {
            return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);
        }

        // Uczestnicy muszą istnieć; blokada mieszania dzieci i dorosłych.
        $participants = DB::table('users')
            ->whereIn('UsersID', $validated['participantIDs'])
            ->where('Cancelled', 0)
            ->get(['UsersID', 'DateOfBirdth']);

        if ($participants->count() !== count(array_unique($validated['participantIDs']))) {
            return response()->json([
                'success' => false,
                'error'   => 'PARTICIPANT_NOT_FOUND',
                'message' => 'Któryś z uczestników nie istnieje.',
            ], 422);
        }

        $categories = $participants
            ->map(fn ($p) => $this->ageCategory($p->DateOfBirdth))
            ->filter(fn ($c) => $c !== 'unknown')
            ->unique();

        if ($categories->contains('child') && $categories->contains('adult')) {
            return response()->json([
                'success' => false,
                'error'   => 'MIXED_PARTICIPANTS',
                'message' => 'Nie można łączyć dzieci i dorosłych w jednej rezerwacji. '
                    . 'Zrób osobne rezerwacje.',
            ], 422);
        }

        // Transakcja z blokadą wiersza sali — serializuje równoległe próby
        // rezerwacji tej samej sali (ochrona przed race condition).
        try {
            $reservation = DB::transaction(function () use ($validated, $authUserId, $room) {
                DB::table('classrooms')
                    ->where('classRoomsID', (int) $room->classRoomsID)
                    ->lockForUpdate()
                    ->first();

                $conflict = $this->availabilityConflict(
                    (int) $validated['localizationsID'],
                    (int) $validated['classRoomsID'],
                    $validated['date'],
                    $validated['timeFrom'],
                    $validated['timeTo'],
                    $validated['mode']
                );

                if ($conflict !== null) {
                    throw new RoomUnavailableException($conflict);
                }

                $holdMinutes = (int) config('room_reservations.hold_minutes', 15);

                $reservation = RoomReservation::create([
                    'instructor_users_id' => $authUserId,
                    'localizations_id'    => (int) $validated['localizationsID'],
                    'class_rooms_id'      => (int) $validated['classRoomsID'],
                    'reservation_date'    => $validated['date'],
                    'time_from'           => $validated['timeFrom'],
                    'time_to'             => $validated['timeTo'],
                    'mode'                => $validated['mode'],
                    'status'              => RoomReservation::STATUS_PENDING,
                    'expires_at'          => now()->addMinutes($holdMinutes),
                    'product_id'          => $validated['productID'] ?? null,
                ]);

                foreach (array_unique($validated['participantIDs']) as $pid) {
                    $reservation->participants()->create(['users_id' => (int) $pid]);
                }

                return $reservation;
            });
        } catch (RoomUnavailableException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'SLOT_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 409);
        }

        Log::info('[ROOM-RESERVATIONS] Hold created', [
            'reservation_id' => $reservation->id,
            'instructor'     => $authUserId,
            'room'           => $reservation->class_rooms_id,
            'mode'           => $reservation->mode,
        ]);

        // TODO (etap płatności): naliczenia per uczestnik (CRM Orders),
        // linki płatności, push+e-mail, zapis terminu do harmonogramu CRM.

        return response()->json([
            'success'     => true,
            'reservation' => $this->presentReservation($reservation->fresh('participants')),
        ], 201);
    }

    /**
     * GET /api/instructor/room-reservations/{id}
     *
     * Status rezerwacji: uczestnicy, kto zapłacił, ile czasu zostało.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (empty($this->employeeIds($request))) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $reservation = RoomReservation::with('participants')
            ->where('id', $id)
            ->where('instructor_users_id', (int) $request->user()->getKey()) // anty-IDOR
            ->first();

        if (!$reservation) {
            return response()->json(['success' => false, 'error' => 'NOT_FOUND'], 404);
        }

        return response()->json([
            'success'     => true,
            'reservation' => $this->presentReservation($reservation),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Sprawdza kolizję slotu dla podanego trybu. Zwraca komunikat błędu
     * albo null, gdy slot jest dostępny. Wołane WEWNĄTRZ transakcji z
     * zablokowanym wierszem sali.
     */
    private function availabilityConflict(
        int $localizationsId,
        int $classRoomsId,
        string $date,
        string $timeFrom,
        string $timeTo,
        string $mode
    ): ?string {
        // 1. Realne zajęcia z CRM (grupy, podnajem, indywidualne) — blokują
        //    oba tryby (współdzielona nie może kolidować z zajęciami grupowymi).
        try {
            $resp = $this->crmClient()->get('/CrmToMobileSync/getRoomOccupancyMobile', [
                'localizationsID'         => $localizationsId,
                'dateFrom'                => $date,
                'dateTo'                  => $date,
                'current_LocalizationsID' => '0',
            ]);
            $occupancy = collect($resp->json('body') ?: []);
        } catch (\Exception $e) {
            Log::error('Room reservation store: CRM occupancy re-check failed', [
                'error' => $e->getMessage(),
            ]);
            return 'Nie udało się zweryfikować dostępności sali. Spróbuj ponownie.';
        }

        $crmOverlap = $occupancy->contains(function ($row) use ($classRoomsId, $timeFrom, $timeTo) {
            return (int) ($row['classRoomsID'] ?? 0) === $classRoomsId
                && $this->timeOverlaps(
                    substr((string) ($row['timeFrom'] ?? ''), 0, 5),
                    substr((string) ($row['timeTo'] ?? ''), 0, 5),
                    $timeFrom,
                    $timeTo
                );
        });

        if ($crmOverlap) {
            return 'Sala jest w tym czasie zajęta przez zajęcia w harmonogramie.';
        }

        // 2. Lokalne holdy (tymczasowe rezerwacje) nakładające się na slot.
        $holds = RoomReservation::query()
            ->where('class_rooms_id', $classRoomsId)
            ->where('reservation_date', $date)
            ->whereIn('status', RoomReservation::ACTIVE_STATUSES)
            ->where(function ($q) {
                // pending liczy się tylko dopóki nie wygasł
                $q->where('status', '!=', RoomReservation::STATUS_PENDING)
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['mode', 'time_from', 'time_to']);

        $overlapping = $holds->filter(fn ($h) => $this->timeOverlaps(
            substr((string) $h->time_from, 0, 5),
            substr((string) $h->time_to, 0, 5),
            $timeFrom,
            $timeTo
        ));

        if ($mode === 'exclusive') {
            if ($overlapping->isNotEmpty()) {
                return 'Sala jest już rezerwowana w tym czasie. Wybierz inny termin lub tryb współdzielony.';
            }
            return null;
        }

        // shared
        if ($overlapping->contains(fn ($h) => $h->mode === 'exclusive')) {
            return 'Sala jest zarezerwowana na wyłączność w tym czasie.';
        }

        $sharedMax = (int) config('room_reservations.shared_max', 5);
        $sharedCount = $overlapping->where('mode', 'shared')->count();
        if ($sharedCount >= $sharedMax) {
            return "Osiągnięto limit rezerwacji współdzielonych ({$sharedMax}) dla tego slotu.";
        }

        return null;
    }

    /** Aktywne holdy zgrupowane po sali (do nałożenia na widok dostępności). */
    private function activeHoldsByRoom(int $localizationsId, string $date)
    {
        $sharedMax = (int) config('room_reservations.shared_max', 5);

        return RoomReservation::query()
            ->where('localizations_id', $localizationsId)
            ->where('reservation_date', $date)
            ->whereIn('status', RoomReservation::ACTIVE_STATUSES)
            ->where(function ($q) {
                $q->where('status', '!=', RoomReservation::STATUS_PENDING)
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['class_rooms_id', 'mode', 'time_from', 'time_to'])
            ->groupBy('class_rooms_id')
            ->map(fn ($group) => $group->map(fn ($h) => [
                'timeFrom'   => substr((string) $h->time_from, 0, 5),
                'timeTo'     => substr((string) $h->time_to, 0, 5),
                'reasonType' => 'hold',
                'label'      => $h->mode === 'shared'
                    ? 'Rezerwacja współdzielona (limit ' . $sharedMax . ')'
                    : 'Rezerwacja w toku',
            ])->values());
    }

    private function presentReservation(RoomReservation $reservation): array
    {
        $secondsLeft = $reservation->status === RoomReservation::STATUS_PENDING
            ? max(0, now()->diffInSeconds($reservation->expires_at, false))
            : 0;

        return [
            'id'            => $reservation->id,
            'status'        => $reservation->status,
            'mode'          => $reservation->mode,
            'date'          => $reservation->reservation_date?->format('Y-m-d'),
            'timeFrom'      => substr((string) $reservation->time_from, 0, 5),
            'timeTo'        => substr((string) $reservation->time_to, 0, 5),
            'classRoomsID'  => (int) $reservation->class_rooms_id,
            'secondsLeft'   => (int) $secondsLeft,
            'participants'  => $reservation->participants->map(fn ($p) => [
                'usersID'    => (int) $p->users_id,
                'paid'       => $p->paid_at !== null,
                'paymentUrl' => $p->payment_url,
            ])->values(),
        ];
    }

    /** Kategoria wieku wg progu z configu; null data → 'unknown'. */
    private function ageCategory(?string $dateOfBirth): string
    {
        if ($dateOfBirth === null || $dateOfBirth === '' || str_starts_with($dateOfBirth, '0000')) {
            return 'unknown';
        }

        try {
            $age = \Carbon\Carbon::parse($dateOfBirth)->age;
        } catch (\Exception $e) {
            return 'unknown';
        }

        return $age < (int) config('room_reservations.child_max_age', 18) ? 'child' : 'adult';
    }

    /** Nachodzenie przedziałów HH:MM (półotwarte: koniec == początek nie koliduje). */
    private function timeOverlaps(string $aFrom, string $aTo, string $bFrom, string $bTo): bool
    {
        if ($aFrom === '' || $aTo === '') {
            return false;
        }
        return $aFrom < $bTo && $aTo > $bFrom;
    }

    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits === '' ? null : '*** *** ' . substr($digits, -3);
    }

    private function crmClient(): CrmClient
    {
        return app(CrmClient::class);
    }

    /**
     * Zajęty przedział z powodem: grupa (nazwa), instruktor (imię i nazwisko
     * z mirrorów employees+users), podnajem lub inny wpis harmonogramu.
     */
    private function presentBusySlot(array $row): array
    {
        $groupName = trim((string) ($row['groupName'] ?? ''));
        $productsId = (int) ($row['productsID'] ?? 0);
        $instructorNames = $this->instructorNames((string) ($row['instructorsIDList'] ?? ''));

        if ($productsId === 8) {
            $reasonType = 'rental';
            $label = $groupName !== '' ? $groupName : 'Podnajem sali';
        } elseif ($productsId === 6) {
            $reasonType = 'individual';
            $label = $instructorNames !== '' ? $instructorNames : ($groupName !== '' ? $groupName : 'Zajęcia indywidualne');
        } elseif ($groupName !== '') {
            $reasonType = 'group';
            $label = $groupName;
        } elseif ($instructorNames !== '') {
            $reasonType = 'instructor';
            $label = $instructorNames;
        } else {
            $reasonType = 'other';
            $label = 'Zajęte';
        }

        return [
            'timeFrom'   => substr((string) ($row['timeFrom'] ?? ''), 0, 5),
            'timeTo'     => substr((string) ($row['timeTo'] ?? ''), 0, 5),
            'reasonType' => $reasonType,
            'label'      => $label,
        ];
    }

    /** Imiona i nazwiska instruktorów z CSV EmployeesID (mirror employees→users). */
    private function instructorNames(string $employeesIdCsv): string
    {
        $ids = collect(explode(',', str_replace(' ', '', $employeesIdCsv)))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return '';
        }

        return DB::table('employees as e')
            ->join('users as u', 'u.UsersID', '=', 'e.UsersID')
            ->whereIn('e.EmployeesID', $ids->all())
            ->pluck('u.fullName')
            ->filter()
            ->implode(', ');
    }

    /** EmployeesID powiązane z zalogowanym użytkownikiem (instruktorem). */
    private function employeeIds(Request $request): array
    {
        $usersId = (int) $request->user()->getKey();
        return Employee::where('UsersID', $usersId)
            ->where(function ($q) {
                $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
            })
            ->pluck('EmployeesID')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
