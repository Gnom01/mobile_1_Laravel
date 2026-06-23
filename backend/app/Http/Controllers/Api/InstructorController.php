<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InstructorAnnouncement;
use App\Models\InstructorMessage;
use App\Models\InstructorReport;
use App\Models\InstructorScheduleChange;
use App\Services\FirebasePushService;
use App\Support\SchoolManagerResolver;
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
    /** Ile dni wstecz uznajemy grupę za „aktywną" (na podstawie ostatnich zajęć). */
    private const ACTIVE_LOOKBACK_DAYS = 120;

    /** @var FirebasePushService */
    private $push;

    /** Cache wyznaczonych grup per zestaw EmployeesID (w obrębie requestu). */
    private array $headingCache = [];

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

        $groups = $this->instructorGroupRows($employeeIds);

        $headingIds = $groups->pluck('coursesHeadingsID')->all();
        $counts = $this->participantCounts($headingIds);

        $body = $groups->map(fn ($g) => [
            'id'            => (int) $g->coursesHeadingsID,
            'groupId'       => (int) $g->coursesHeadingsID,
            'name'          => $g->courseHeadingName,
            'frequency'     => '',
            'time'          => '',
            'localization'  => $g->localizationName,
            'instructors'   => '',
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
     * GET /api/instructor/participants/{userId}/relations
     * Osoby powiązane uczestnika (rodzice/opiekunowie i dzieci) — do podglądu
     * „kto jeszcze zobaczy wiadomość". Dostępne tylko, gdy uczestnik należy do
     * grup zalogowanego instruktora.
     */
    public function participantRelations(Request $request, int $userId): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds) || !$this->instructorHasParticipant($employeeIds, $userId)) {
            return response()->json(['message' => 'Brak dostępu do tego uczestnika.'], 403);
        }

        // Rodzice/opiekunowie: usersrelations.UsersID = uczestnik → Parent_UsersID.
        $parentIds = DB::table('usersrelations')
            ->where('UsersID', $userId)->where('Cancelled', 0)
            ->pluck('Parent_UsersID')->map(fn ($v) => (int) $v)->all();
        // Dzieci/podopieczni: usersrelations.Parent_UsersID = uczestnik → UsersID.
        $childIds = DB::table('usersrelations')
            ->where('Parent_UsersID', $userId)->where('Cancelled', 0)
            ->pluck('UsersID')->map(fn ($v) => (int) $v)->all();

        $ids = array_values(array_unique(array_filter(
            array_merge($parentIds, $childIds),
            fn ($v) => $v > 0 && $v !== $userId
        )));
        if (empty($ids)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $users = DB::table('users')
            ->whereIn('UsersID', $ids)
            ->where('Cancelled', 0)
            ->get(['UsersID', 'guid', 'FirstName', 'LastName']);

        $body = $users->map(fn ($u) => [
            'usersID'   => (int) $u->UsersID,
            'guid'      => $u->guid,
            'firstName' => $u->FirstName,
            'lastName'  => $u->LastName,
            'fullName'  => trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? '')),
            'relation'  => in_array((int) $u->UsersID, $parentIds, true) ? 'rodzic/opiekun' : 'dziecko',
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
        $groupId = null;
        $participantUserId = null;
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

        // Zapisz historię, żeby instruktor widział, co i do kogo wysłał.
        $message = InstructorMessage::create([
            'instructor_user_id'  => (int) $request->user()->getKey(),
            'target'              => $data['target'],
            'group_id'            => $groupId,
            'participant_user_id' => $participantUserId,
            'title'               => $title,
            'body'                => $bodyTxt,
            'recipient_count'     => $sent,
        ]);

        return response()->json([
            'status'         => '200',
            'message'        => 'Komunikat wysłany.',
            'recipientCount' => $sent,
            'data'           => $this->mapMessage($message->fresh(), $this->groupNamesMap()),
        ]);
    }

    /**
     * GET /api/instructor/messages
     * Historia komunikatów wysłanych przez zalogowanego instruktora.
     */
    public function messages(Request $request): JsonResponse
    {
        $instructorUserId = (int) $request->user()->getKey();

        $rows = InstructorMessage::where('instructor_user_id', $instructorUserId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $groupNames = $this->groupNamesMap();
        $body = $rows->map(fn ($r) => $this->mapMessage($r, $groupNames))->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    private function mapMessage(InstructorMessage $r, array $groupNames): array
    {
        return [
            'id'              => (int) $r->id,
            'target'          => $r->target,
            'groupId'         => $r->group_id ? (int) $r->group_id : null,
            'groupName'       => $r->group_id ? ($groupNames[$r->group_id] ?? null) : null,
            'participantId'   => $r->participant_user_id ? (int) $r->participant_user_id : null,
            'participantName' => $r->participant_user_id ? $this->userName((int) $r->participant_user_id) : null,
            'title'           => $r->title,
            'body'            => $r->body,
            'recipientCount'  => (int) $r->recipient_count,
            'createdAt'       => optional($r->created_at)->toIso8601String(),
        ];
    }

    /**
     * GET /api/instructor/schedule?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Harmonogram instruktora z `scheduleseventssettlements` wg jego grup
     * (coursesHeadings). Domyślnie najbliższe 14 dni.
     */
    public function schedule(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $validator = Validator::make($request->all(), [
            'from'    => ['nullable', 'date_format:Y-m-d'],
            'to'      => ['nullable', 'date_format:Y-m-d'],
            'groupId' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $from = $request->input('from') ?: now()->format('Y-m-d');
        $to   = $request->input('to') ?: now()->addDays(14)->format('Y-m-d');

        $groupIds = $this->instructorHeadingIds($employeeIds);
        if (empty($groupIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        // Opcjonalne zawężenie do jednej grupy (jeśli należy do instruktora).
        if ($request->filled('groupId')) {
            $gid = (int) $request->input('groupId');
            if (!in_array($gid, $groupIds, true)) {
                return response()->json(['message' => 'Brak dostępu do tej grupy.'], 403);
            }
            $groupIds = [$gid];
        }

        $ghPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));

        $rows = DB::select(
            "
            SELECT
                ses.schedulesEventsSettlementsID,
                ses.schedulesID,
                ses.coursesHeadingsID,
                ses.eventDate,
                ses.timeFrom,
                ses.timeTo,
                ses.sheduleItemTypeDVID,
                ses.eventsSettlementsStatusesDVID,
                COALESCE(course.courseHeadingName, ch.CourseHeadingName, xs.groupname, '') AS title,
                COALESCE(xs.groupname, course.courseHeadingName, ch.CourseHeadingName, '') AS groupName,
                COALESCE(l.LocalizationName, course.localizationName, xs.location, '') AS locationName,
                COALESCE(xs.instructors, course.instructorsList, '') AS instructors
            FROM scheduleseventssettlements ses
            LEFT JOIN xschedules xs
                ON xs.id = ses.schedulesID
            LEFT JOIN courses course
                ON course.coursesHeadingsID = ses.coursesHeadingsID
            LEFT JOIN coursesheadings ch
                ON ch.CoursesHeadingsID = ses.coursesHeadingsID
                AND ch.Cancelled = 0
            LEFT JOIN localizations l
                ON l.LocalizationsID = ses.localizationsID
                AND l.Cancelled = 0
            WHERE ses.cancelled = 0
                AND ses.coursesHeadingsID IN ($ghPlaceholders)
                AND ses.eventDate >= ?
                AND ses.eventDate <= ?
            ORDER BY ses.eventDate ASC, ses.timeFrom ASC
            ",
            array_merge($groupIds, [$from, $to])
        );

        $counts = $this->participantCounts($groupIds);

        $body = array_map(function ($row) use ($counts) {
            return [
                'id'                => (int) $row->schedulesEventsSettlementsID,
                'scheduleId'        => (int) $row->schedulesID,
                'coursesHeadingsID' => (int) $row->coursesHeadingsID,
                'date'              => $row->eventDate,
                'timeFrom'          => substr((string) $row->timeFrom, 0, 5),
                'timeTo'            => substr((string) $row->timeTo, 0, 5),
                'title'             => (string) $row->title,
                'groupName'         => (string) $row->groupName,
                'locationName'      => (string) $row->locationName,
                'instructors'       => (string) $row->instructors,
                'itemTypeDVID'      => (int) $row->sheduleItemTypeDVID,
                'statusDVID'        => (int) $row->eventsSettlementsStatusesDVID,
                'count'             => (int) ($counts[$row->coursesHeadingsID] ?? 0),
            ];
        }, $rows);

        return response()->json([
            'status'      => '200',
            'body'        => $body,
            'recordCount' => count($body),
            'range'       => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * GET /api/instructor/participants
     * Wszyscy uczestnicy ze wszystkich grup instruktora (do budowy czatu
     * grupowego). Każdy z listą grup, w których uczestniczy.
     */
    public function allParticipants(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $groups = $this->instructorGroupRows($employeeIds);
        $groupNameById = [];
        foreach ($groups as $g) {
            $groupNameById[(int) $g->coursesHeadingsID] = $g->courseHeadingName;
        }
        $groupIds = array_keys($groupNameById);
        if (empty($groupIds)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        // userId => [groupId,...] z umów.
        $map = [];
        $rows = DB::table('contracts')
            ->whereIn('coursesHeadingsID', $groupIds)
            ->where('cancelled', 0)
            ->get(['usersID', 'coursesHeadingsID']);
        foreach ($rows as $r) {
            $map[(int) $r->usersID][(int) $r->coursesHeadingsID] = true;
        }
        if (empty($map)) {
            return response()->json(['status' => '200', 'body' => [], 'recordCount' => 0]);
        }

        $users = DB::table('users')
            ->whereIn('UsersID', array_keys($map))
            ->where('Cancelled', 0)
            ->get(['UsersID', 'guid', 'FirstName', 'LastName']);

        $body = $users->map(function ($u) use ($map, $groupNameById) {
            $gids = array_keys($map[(int) $u->UsersID] ?? []);
            $groupNames = array_values(array_filter(array_map(
                fn ($gid) => $groupNameById[$gid] ?? null,
                $gids
            )));
            return [
                'usersID'    => (int) $u->UsersID,
                'guid'       => $u->guid,
                'firstName'  => $u->FirstName,
                'lastName'   => $u->LastName,
                'fullName'   => trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? '')),
                'groupIds'   => array_values($gids),
                'groupNames' => $groupNames,
            ];
        })
        ->sortBy('fullName')
        ->values();

        return response()->json([
            'status'      => '200',
            'body'        => $body,
            'recordCount' => $body->count(),
        ]);
    }

    /**
     * GET /api/instructor/change-types
     * Słownik typów zmian w harmonogramie (multiselect w kreatorze „+").
     */
    public function changeTypes(): JsonResponse
    {
        return response()->json([
            'status' => '200',
            'body'   => array_values((array) config('instructor.change_types', [])),
        ]);
    }

    /**
     * GET /api/instructor/schedule-changes
     * Historia zgłoszonych zmian zalogowanego instruktora.
     */
    public function scheduleChanges(Request $request): JsonResponse
    {
        $instructorUserId = (int) $request->user()->getKey();

        $rows = InstructorScheduleChange::where('instructor_user_id', $instructorUserId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $groupNames = $this->groupNamesMap();

        $body = $rows->map(fn ($r) => $this->mapScheduleChange($r, $groupNames))->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/instructor/schedule-changes
     * Body: changeTypes[] (klucze), groupIds[] (coursesHeadingsID), date,
     *       title?, note?
     *
     * Zapisuje zmianę, wysyła push do uczestników wybranych grup oraz do
     * menadżera(ów) szkoły.
     */
    public function storeScheduleChange(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['message' => 'Konto nie jest powiązane z instruktorem.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'changeTypes'   => ['required', 'array', 'min:1'],
            'changeTypes.*' => ['string', 'max:40'],
            'groupIds'      => ['required', 'array', 'min:1'],
            'groupIds.*'    => ['integer'],
            'date'          => ['required', 'date_format:Y-m-d'],
            'title'         => ['nullable', 'string', 'max:160'],
            'note'          => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $instructorUserId = (int) $request->user()->getKey();

        // Walidacja typów względem słownika.
        $allowedTypes = array_column((array) config('instructor.change_types', []), 'key');
        $changeTypes  = array_values(array_intersect($data['changeTypes'], $allowedTypes));
        if (empty($changeTypes)) {
            return response()->json(['message' => 'Nieznany typ zmiany.'], 422);
        }

        // Walidacja przynależności wszystkich grup do instruktora.
        $ownedGroupIds = $this->instructorHeadingIds($employeeIds);
        $groupIds = array_values(array_unique(array_map('intval', $data['groupIds'])));
        $invalid  = array_diff($groupIds, $ownedGroupIds);
        if (!empty($invalid)) {
            return response()->json(['message' => 'Brak dostępu do części wybranych grup.'], 403);
        }

        // Odbiorcy: uczestnicy wszystkich wybranych grup.
        $recipientIds = [];
        foreach ($groupIds as $gid) {
            $recipientIds = array_merge($recipientIds, $this->groupParticipantIds($gid));
        }
        $recipientIds = array_values(array_unique(array_filter($recipientIds)));

        // Menadżer(owie) szkoły.
        $managerIds = SchoolManagerResolver::forGroups($groupIds, [$instructorUserId]);

        $typeLabels = $this->labelsForKeys($changeTypes, 'change_types');
        $groupNames = $this->groupNamesMap();
        $groupLabel = $this->namesForIds($groupIds, $groupNames);

        $title = $data['title'] ?? ('Zmiana w harmonogramie: ' . implode(', ', $typeLabels));
        $dateHuman = $data['date'];
        $bodyParts = [
            implode(', ', $typeLabels),
            'Grupy: ' . $groupLabel,
            'Data: ' . $dateHuman,
        ];
        if (!empty($data['note'])) {
            $bodyParts[] = $data['note'];
        }
        $pushBody = implode("\n", $bodyParts);

        $change = InstructorScheduleChange::create([
            'instructor_user_id' => $instructorUserId,
            'change_types'       => $changeTypes,
            'group_ids'          => $groupIds,
            'event_date'         => $data['date'],
            'title'              => $title,
            'note'               => $data['note'] ?? null,
            'recipient_count'    => count($recipientIds),
            'crm_status'         => 'pending',
        ]);

        // Push do uczestników.
        $sent = $this->pushMany($recipientIds, $title, $pushBody, 'instructor');

        // Push do menadżera(ów) — z adnotacją od kogo.
        $instructorName = $this->userName($instructorUserId);
        $managerTitle = 'Zgłoszona zmiana: ' . $instructorName;
        $managerSent = $this->pushMany($managerIds, $managerTitle, $pushBody, 'instructor_manager');

        $change->update([
            'manager_notified_count' => $managerSent,
        ]);

        return response()->json([
            'status'           => '200',
            'message'          => 'Zmiana zapisana i wysłana.',
            'data'             => $this->mapScheduleChange($change->fresh(), $groupNames),
            'recipientCount'   => $sent,
            'managerNotified'  => $managerSent,
        ], 201);
    }

    /**
     * GET /api/instructor/report-types
     * Słownik typów zgłoszeń.
     */
    public function reportTypes(): JsonResponse
    {
        return response()->json([
            'status' => '200',
            'body'   => array_values((array) config('instructor.report_types', [])),
        ]);
    }

    /**
     * GET /api/instructor/reports
     * Historia zgłoszeń instruktora.
     */
    public function reports(Request $request): JsonResponse
    {
        $instructorUserId = (int) $request->user()->getKey();

        $rows = InstructorReport::where('instructor_user_id', $instructorUserId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $groupNames = $this->groupNamesMap();
        $body = $rows->map(fn ($r) => $this->mapReport($r, $groupNames))->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
    }

    /**
     * POST /api/instructor/reports
     * Body: reportTypes[] (klucze), description, date?, groupIds?[], title?
     * Powiadamia menadżera szkoły.
     */
    public function storeReport(Request $request): JsonResponse
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return response()->json(['message' => 'Konto nie jest powiązane z instruktorem.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reportTypes'   => ['required', 'array', 'min:1'],
            'reportTypes.*' => ['string', 'max:40'],
            'description'   => ['required', 'string', 'max:2000'],
            'date'          => ['nullable', 'date_format:Y-m-d'],
            'title'         => ['nullable', 'string', 'max:160'],
            'groupIds'      => ['nullable', 'array'],
            'groupIds.*'    => ['integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Nieprawidłowe dane.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $instructorUserId = (int) $request->user()->getKey();

        $allowedTypes = array_column((array) config('instructor.report_types', []), 'key');
        $reportTypes  = array_values(array_intersect($data['reportTypes'], $allowedTypes));
        if (empty($reportTypes)) {
            return response()->json(['message' => 'Nieznany typ zgłoszenia.'], 422);
        }

        $groupIds = [];
        if (!empty($data['groupIds'])) {
            $ownedGroupIds = $this->instructorHeadingIds($employeeIds);
            $groupIds = array_values(array_unique(array_map('intval', $data['groupIds'])));
            $invalid  = array_diff($groupIds, $ownedGroupIds);
            if (!empty($invalid)) {
                return response()->json(['message' => 'Brak dostępu do części wybranych grup.'], 403);
            }
        }

        $typeLabels = $this->labelsForKeys($reportTypes, 'report_types');
        $groupNames = $this->groupNamesMap();

        $title = $data['title'] ?? ('Zgłoszenie: ' . implode(', ', $typeLabels));

        $report = InstructorReport::create([
            'instructor_user_id' => $instructorUserId,
            'report_types'       => $reportTypes,
            'group_ids'          => $groupIds ?: null,
            'event_date'         => $data['date'] ?? null,
            'title'              => $title,
            'description'        => $data['description'],
            'status'             => 'new',
        ]);

        // Powiadom menadżera(ów).
        $managerIds = SchoolManagerResolver::forGroups($groupIds, [$instructorUserId]);
        $instructorName = $this->userName($instructorUserId);
        $managerBody = implode("\n", array_filter([
            implode(', ', $typeLabels),
            $data['date'] ?? null ? ('Data: ' . $data['date']) : null,
            $data['description'],
        ]));
        $managerSent = $this->pushMany($managerIds, 'Zgłoszenie: ' . $instructorName, $managerBody, 'instructor_manager');

        $report->update(['manager_notified_count' => $managerSent]);

        return response()->json([
            'status'          => '200',
            'message'         => 'Zgłoszenie wysłane.',
            'data'            => $this->mapReport($report->fresh(), $groupNames),
            'managerNotified' => $managerSent,
        ], 201);
    }

    /**
     * GET /api/instructor/announcements
     * Blok ogłoszeń dla instruktora (wydarzenia, zbiórki, szkolenia).
     */
    public function announcements(Request $request): JsonResponse
    {
        $localizationIds = $this->instructorLocalizationIds($request);

        $rows = InstructorAnnouncement::active()
            ->where(function ($q) use ($localizationIds) {
                $q->where('localizations_id', 0);
                if (!empty($localizationIds)) {
                    $q->orWhereIn('localizations_id', $localizationIds);
                }
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->orderByRaw('event_at IS NULL')
            ->orderBy('event_at')
            ->limit(50)
            ->get();

        $body = $rows->map(fn ($a) => [
            'id'        => (int) $a->id,
            'title'     => $a->title,
            'body'      => $a->body,
            'kind'      => $a->kind,
            'eventAt'   => optional($a->event_at)->toIso8601String(),
            'startsAt'  => optional($a->starts_at)->toIso8601String(),
            'endsAt'    => optional($a->ends_at)->toIso8601String(),
        ])->values();

        return response()->json(['status' => '200', 'body' => $body, 'recordCount' => $body->count()]);
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

    /**
     * coursesHeadingsID grup prowadzonych przez instruktora.
     *
     * Źródłem jest pełny harmonogram `scheduleseventssettlements`
     * (`instructorsIDList`, FIND_IN_SET z EmployeesID) — tabela `courses`
     * zawiera tylko wąski podzbiór „oferty WWW" i nie nadaje się tu.
     * Zawężamy do grup aktywnych: zdarzenia od (dziś − ACTIVE_LOOKBACK_DAYS).
     *
     * @return array<int>
     */
    private function instructorHeadingIds(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }
        $cacheKey = implode(',', $employeeIds);
        if (isset($this->headingCache[$cacheKey])) {
            return $this->headingCache[$cacheKey];
        }

        $cutoff = now()->subDays(self::ACTIVE_LOOKBACK_DAYS)->format('Y-m-d');

        $ids = DB::table('scheduleseventssettlements')
            ->where('cancelled', 0)
            ->where('eventDate', '>=', $cutoff)
            ->where(function ($q) use ($employeeIds) {
                foreach ($employeeIds as $eid) {
                    $q->orWhereRaw('FIND_IN_SET(?, instructorsIDList)', [$eid]);
                }
            })
            ->distinct()
            ->pluck('coursesHeadingsID')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        return $this->headingCache[$cacheKey] = $ids;
    }

    /** Grupy instruktora jako wiersze z coursesheadings (id, nazwa, lokalizacja). */
    private function instructorGroupRows(array $employeeIds)
    {
        $headingIds = $this->instructorHeadingIds($employeeIds);
        if (empty($headingIds)) {
            return collect();
        }

        $rows = DB::table('coursesheadings')
            ->whereIn('CoursesHeadingsID', $headingIds)
            ->where('Cancelled', 0)
            ->orderBy('CourseHeadingName')
            ->get(['CoursesHeadingsID', 'CourseHeadingName', 'LocalizationsID']);

        $locNames = DB::table('localizations')
            ->whereIn('LocalizationsID', $rows->pluck('LocalizationsID')->filter()->unique()->all() ?: [0])
            ->pluck('LocalizationName', 'LocalizationsID');

        return $rows->map(fn ($r) => (object) [
            'coursesHeadingsID' => (int) $r->CoursesHeadingsID,
            'courseHeadingName' => (string) $r->CourseHeadingName,
            'localizationsID'   => (int) $r->LocalizationsID,
            'localizationName'  => (string) ($locNames[$r->LocalizationsID] ?? ''),
        ]);
    }

    private function ownsGroup(array $employeeIds, int $groupId): bool
    {
        return in_array($groupId, $this->instructorHeadingIds($employeeIds), true);
    }

    /** UsersID uczestników grupy (aktywne zapisy). */
    private function groupParticipantIds(int $groupId): array
    {
        return DB::table('contracts')
            ->where('coursesHeadingsID', $groupId)
            ->where('cancelled', 0)
            ->distinct()
            ->pluck('usersID')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Liczby uczestników dla zbioru grup (jedno zapytanie zgrupowane). */
    private function participantCounts(array $headingIds): array
    {
        if (empty($headingIds)) {
            return [];
        }
        return DB::table('contracts')
            ->select('coursesHeadingsID', DB::raw('COUNT(DISTINCT usersID) as c'))
            ->whereIn('coursesHeadingsID', $headingIds)
            ->where('cancelled', 0)
            ->groupBy('coursesHeadingsID')
            ->pluck('c', 'coursesHeadingsID')
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
        $groupIds = $this->instructorHeadingIds($employeeIds);
        if (empty($groupIds)) {
            return false;
        }
        return DB::table('contracts')
            ->whereIn('coursesHeadingsID', $groupIds)
            ->where('usersID', $participantUserId)
            ->where('cancelled', 0)
            ->exists();
    }

    /** Mapa coursesHeadingsID => nazwa grupy (z coursesheadings — pełny zbiór). */
    private function groupNamesMap(): array
    {
        return DB::table('coursesheadings')
            ->where('Cancelled', 0)
            ->pluck('CourseHeadingName', 'CoursesHeadingsID')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    /** "Nazwa A, Nazwa B (+2)" dla listy ID grup. */
    private function namesForIds(array $groupIds, array $groupNames): string
    {
        $names = [];
        foreach ($groupIds as $gid) {
            $names[] = $groupNames[$gid] ?? ('#' . $gid);
        }
        if (count($names) <= 3) {
            return implode(', ', $names);
        }
        $head = array_slice($names, 0, 3);
        return implode(', ', $head) . ' (+' . (count($names) - 3) . ')';
    }

    /** Etykiety dla kluczy słownika z config('instructor.*'). */
    private function labelsForKeys(array $keys, string $dictionary): array
    {
        $dict = (array) config('instructor.' . $dictionary, []);
        $byKey = [];
        foreach ($dict as $row) {
            $byKey[$row['key']] = $row['label'];
        }
        return array_values(array_map(fn ($k) => $byKey[$k] ?? $k, $keys));
    }

    /** Wysyła push do listy UsersID, zwraca liczbę udanych. */
    private function pushMany(array $userIds, string $title, string $body, string $category): int
    {
        $sent = 0;
        foreach (array_values(array_unique(array_filter($userIds))) as $uid) {
            try {
                $this->push->sendToUser((int) $uid, $title, $body, $category);
                $sent++;
            } catch (\Throwable $e) {
                // pojedynczy błąd nie przerywa wysyłki
            }
        }
        return $sent;
    }

    private function userName(int $userId): string
    {
        $u = DB::table('users')->where('UsersID', $userId)->first(['FirstName', 'LastName']);
        if (!$u) {
            return 'Instruktor';
        }
        $name = trim(($u->FirstName ?? '') . ' ' . ($u->LastName ?? ''));
        return $name !== '' ? $name : 'Instruktor';
    }

    /** Lokalizacje (szkoły) instruktora — z jego rekordów employees i grup. */
    private function instructorLocalizationIds(Request $request): array
    {
        $employeeIds = $this->employeeIds($request);
        if (empty($employeeIds)) {
            return [];
        }

        $fromEmployees = DB::table('employees')
            ->whereIn('EmployeesID', $employeeIds)
            ->where('LocalizationsID', '>', 0)
            ->pluck('LocalizationsID')->map(fn ($v) => (int) $v)->all();

        $groupIds = $this->instructorHeadingIds($employeeIds);
        $fromHeadings = empty($groupIds) ? [] : DB::table('coursesheadings')
            ->whereIn('CoursesHeadingsID', $groupIds)
            ->where('LocalizationsID', '>', 0)
            ->pluck('LocalizationsID')->map(fn ($v) => (int) $v)->all();

        return array_values(array_unique(array_merge($fromEmployees, $fromHeadings)));
    }

    private function mapScheduleChange(InstructorScheduleChange $r, array $groupNames): array
    {
        $groupIds = (array) $r->group_ids;
        return [
            'id'              => (int) $r->id,
            'changeTypes'     => (array) $r->change_types,
            'changeLabels'    => $this->labelsForKeys((array) $r->change_types, 'change_types'),
            'groupIds'        => array_map('intval', $groupIds),
            'groupLabel'      => $this->namesForIds(array_map('intval', $groupIds), $groupNames),
            'date'            => optional($r->event_date)->format('Y-m-d'),
            'title'           => $r->title,
            'note'            => $r->note,
            'recipientCount'  => (int) $r->recipient_count,
            'managerNotified' => (int) $r->manager_notified_count,
            'crmStatus'       => $r->crm_status,
            'createdAt'       => optional($r->created_at)->toIso8601String(),
        ];
    }

    private function mapReport(InstructorReport $r, array $groupNames): array
    {
        $groupIds = (array) ($r->group_ids ?? []);
        return [
            'id'              => (int) $r->id,
            'reportTypes'     => (array) $r->report_types,
            'reportLabels'    => $this->labelsForKeys((array) $r->report_types, 'report_types'),
            'groupIds'        => array_map('intval', $groupIds),
            'groupLabel'      => $this->namesForIds(array_map('intval', $groupIds), $groupNames),
            'date'            => optional($r->event_date)->format('Y-m-d'),
            'title'           => $r->title,
            'description'     => $r->description,
            'status'          => $r->status,
            'managerNotified' => (int) $r->manager_notified_count,
            'createdAt'       => optional($r->created_at)->toIso8601String(),
        ];
    }
}
