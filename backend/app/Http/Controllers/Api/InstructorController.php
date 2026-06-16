<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Endpointy instruktora: grupy, uczestnicy grupy i komunikaty push.
 *
 * Reużywa istniejącej infrastruktury push (FirebasePushService::sendToUser),
 * tej samej, której używa OrderApplicationService. Powiadomienia trafiają
 * do kategorii `instructor`, więc pojawiają się w sekcji „Wiadomości od
 * instruktora i szkoły" w aplikacji.
 *
 * Bezpieczeństwo: instruktor może pisać wyłącznie do SWOICH grup i do
 * uczestników tych grup (weryfikacja przynależności).
 */
class InstructorController extends Controller
{
    /** @var FirebasePushService */
    private $push;

    public function __construct(FirebasePushService $push)
    {
        $this->push = $push;
    }

    /**
     * GET /api/instructor/groups
     * Lista grup prowadzonych przez zalogowanego instruktora.
     */
    public function groups(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $groups = $this->instructorGroupsQuery($employeeIds)->get();

        $headingIds = $groups->pluck('coursesHeadingsID')->all();
        $counts = $this->participantCounts($headingIds);

        $body = $groups->map(fn ($g) => [
            'id'            => (int) $g->coursesHeadingsID,
            'groupId'       => (int) $g->coursesHeadingsID,
            'name'          => $g->courseHeadingName,
            'frequency'     => $g->frequency,
            'time'          => $g->courseTimeName,
            'localization'  => $g->localizationName,
            'instructors'   => $g->instructorsList,
            'count'         => (int) ($counts[$g->coursesHeadingsID] ?? 0),
        ])->values();

        return response()->json([
            'status'      => '200',
            'body'        => $body,
            'recordCount' => $body->count(),
        ]);
    }

    /**
     * GET /api/instructor/groups/{groupId}/participants
     * Uczestnicy danej grupy (tylko jeśli grupę prowadzi zalogowany instruktor).
     */
    public function participants(Request $request, int $groupId): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds) || !$this->ownsGroup($employeeIds, $groupId)) {
            return response()->json(['message' => 'Brak dostępu do tej grupy.'], 403);
        }

        $userIds = $this->groupParticipantIds($groupId);
        if (empty($userIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $users = DB::table('users')
            ->whereIn('UsersID', $userIds)
            ->get(['UsersID', 'guid', 'FirstName', 'LastName']);

        $body = $users->map(fn ($u) => [
            'usersID'   => (int) $u->UsersID,
            'guid'      => $u->guid,
            'firstName' => $u->FirstName,
            'lastName'  => $u->LastName,
            'fullName'  => trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? '')),
        ])->values();

        return response()->json([
            'status'      => '200',
            'body'        => $body,
            'recordCount' => $body->count(),
        ]);
    }

    /**
     * POST /api/instructor/messages
     * Wysyła komunikat push do całej grupy lub do jednego uczestnika.
     *
     * Body: target=group|participant, title, body, groupId?, participantGuid?
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target'          => ['required', 'in:group,participant'],
            'title'           => ['required', 'string', 'max:120'],
            'body'            => ['required', 'string', 'max:1000'],
            'groupId'         => ['nullable', 'integer'],
            'participantGuid' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['message' => 'Konto nie jest powiązane z instruktorem.'], 403);
        }

        $data    = $validator->validated();
        $title   = $data['title'];
        $bodyTxt = $data['body'];

        // Wyznacz odbiorców + autoryzacja przynależności do grup instruktora.
        if ($data['target'] === 'group') {
            $groupId = (int) ($data['groupId'] ?? 0);
            if ($groupId <= 0 || !$this->ownsGroup($employeeIds, $groupId)) {
                return response()->json(['message' => 'Brak dostępu do tej grupy.'], 403);
            }
            $recipientIds = $this->groupParticipantIds($groupId);
        } else {
            $participantUserId = $this->resolveParticipantUserId($data['participantGuid'] ?? '');
            if ($participantUserId === null) {
                return response()->json(['message' => 'Nie znaleziono uczestnika.'], 404);
            }
            // Uczestnik musi należeć do którejś z grup instruktora.
            if (!$this->instructorHasParticipant($employeeIds, $participantUserId)) {
                return response()->json(['message' => 'Ten uczestnik nie należy do Twoich grup.'], 403);
            }
            $recipientIds = [$participantUserId];
        }

        $recipientIds = array_values(array_unique(array_filter($recipientIds)));
        if (empty($recipientIds)) {
            return response()->json(['message' => 'Brak odbiorców w tej grupie.'], 422);
        }

        $sent = 0;
        foreach ($recipientIds as $uid) {
            try {
                $this->push->sendToUser((int) $uid, $title, $bodyTxt, 'instructor');
                $sent++;
            } catch (\Throwable $e) {
                // Pojedynczy błąd nie przerywa wysyłki do reszty grupy.
            }
        }

        return response()->json([
            'status'         => '200',
            'message'        => 'Komunikat wysłany.',
            'recipientCount' => $sent,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

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

    /** Bazowe zapytanie o grupy prowadzone przez podane EmployeesID. */
    private function instructorGroupsQuery(array $employeeIds)
    {
        $query = DB::table('courses')->where('websiteStatusesDVID', '!=', 0);
        $query->where(function ($q) use ($employeeIds) {
            foreach ($employeeIds as $eid) {
                $q->orWhereRaw('FIND_IN_SET(?, instructorEmployeesIDList)', [$eid]);
            }
        });
        return $query->orderBy('courseHeadingName');
    }

    private function ownsGroup(array $employeeIds, int $groupId): bool
    {
        return $this->instructorGroupsQuery($employeeIds)
            ->where('coursesHeadingsID', $groupId)
            ->exists();
    }

    /** UsersID uczestników grupy (aktywne zapisy). */
    private function groupParticipantIds(int $groupId): array
    {
        $fromProducts = DB::table('usersproducts')
            ->where('coursesheadingsid', $groupId)
            ->where('cancelled', 0)
            ->distinct()
            ->pluck('usersid')
            ->all();

        $fromSchedules = DB::table('usersschedules')
            ->where('coursesheadingsid', $groupId)
            ->where('cancelled', 0)
            ->distinct()
            ->pluck('usersid')
            ->all();

        return array_values(array_unique(array_map(
            fn ($v) => (int) $v,
            array_merge($fromProducts, $fromSchedules)
        )));
    }

    /** Liczby uczestników dla zbioru grup (jedno zapytanie zgrupowane). */
    private function participantCounts(array $headingIds): array
    {
        if (empty($headingIds)) {
            return [];
        }
        return DB::table('usersproducts')
            ->select('coursesheadingsid', DB::raw('COUNT(DISTINCT usersid) as c'))
            ->whereIn('coursesheadingsid', $headingIds)
            ->where('cancelled', 0)
            ->groupBy('coursesheadingsid')
            ->pluck('c', 'coursesheadingsid')
            ->all();
    }

    /** Z guid lub numerycznego UsersID → UsersID. */
    private function resolveParticipantUserId(string $identifier): ?int
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        if (is_numeric($identifier)) {
            $exists = DB::table('users')->where('UsersID', (int) $identifier)->exists();
            return $exists ? (int) $identifier : null;
        }
        $id = DB::table('users')->where('guid', $identifier)->value('UsersID');
        return $id ? (int) $id : null;
    }

    private function instructorHasParticipant(array $employeeIds, int $participantUserId): bool
    {
        $groupIds = $this->instructorGroupsQuery($employeeIds)->pluck('coursesHeadingsID')->all();
        if (empty($groupIds)) {
            return false;
        }
        $inProducts = DB::table('usersproducts')
            ->whereIn('coursesheadingsid', $groupIds)
            ->where('usersid', $participantUserId)
            ->where('cancelled', 0)
            ->exists();
        if ($inProducts) {
            return true;
        }
        return DB::table('usersschedules')
            ->whereIn('coursesheadingsid', $groupIds)
            ->where('usersid', $participantUserId)
            ->where('cancelled', 0)
            ->exists();
    }
}
