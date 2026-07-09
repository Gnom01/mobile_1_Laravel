<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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

            $result[] = [
                'localizationsID'  => (int) $loc->LocalizationsID,
                'localizationName' => $loc->LocalizationName,
                'city'             => $loc->City,
                'rooms'            => $rooms->map(fn ($room) => [
                    'classRoomsID'  => (int) $room->classRoomsID,
                    'classRoomName' => $room->classRoomName,
                    'busy'          => ($byRoom->get((int) $room->classRoomsID) ?? collect())
                        ->map(fn ($row) => $this->presentBusySlot((array) $row))
                        ->values(),
                ])->values(),
            ];
        }

        return response()->json([
            'success'       => true,
            'date'          => $date,
            'localizations' => $result,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

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
