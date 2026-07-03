<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceReport;
use App\Models\CrmUser;
use App\Models\OutboxEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zgłaszanie nieobecności przez rodzica/uczestnika (ekran CLS_04).
 *
 * Zapis jest lokalny (absence_reports) + zdarzenie w outbox_events, żeby
 * PushOutboxJob mógł je przekazać do CRM, gdy po stronie CRM powstanie
 * endpoint. Instruktor i recepcja widzą zgłoszenie od razu po stronie mobile.
 */
class AbsenceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedulesEventsSettlementsID' => ['required', 'integer', 'min:1'],
            'participantUsersID' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $authUser = $request->user();
        $participantId = (int) $validated['participantUsersID'];

        if (!$this->canActFor($authUser, $participantId)) {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Brak uprawnień do zgłoszenia nieobecności dla tej osoby.',
            ], 403);
        }

        // Zdarzenie musi istnieć, być w przyszłości (lub dziś) i dotyczyć
        // kontraktu uczestnika — ten sam warunek co w kalendarzu.
        $event = DB::selectOne(
            "
            SELECT
                ses.schedulesEventsSettlementsID,
                ses.eventDate,
                ses.timeFrom,
                ses.timeTo,
                COALESCE(course.courseHeadingName, ch.CourseHeadingName, c.courseHeadingName, '') AS title
            FROM scheduleseventssettlements ses
            INNER JOIN contracts c
                ON c.coursesHeadingsID = ses.coursesHeadingsID
                AND c.cancelled = 0
                AND c.usersID = ?
            LEFT JOIN courses course
                ON course.coursesHeadingsID = ses.coursesHeadingsID
            LEFT JOIN coursesheadings ch
                ON ch.CoursesHeadingsID = ses.coursesHeadingsID
                AND ch.Cancelled = 0
            WHERE ses.schedulesEventsSettlementsID = ?
                AND ses.cancelled = 0
                AND (c.contractPeriodFrom IS NULL OR c.contractPeriodFrom <= ses.eventDate)
                AND (c.contractPeriodTo IS NULL OR c.contractPeriodTo >= ses.eventDate)
            LIMIT 1
            ",
            [$participantId, (int) $validated['schedulesEventsSettlementsID']]
        );

        if (!$event) {
            return response()->json([
                'success' => false,
                'error' => 'EVENT_NOT_FOUND',
                'message' => 'Nie znaleziono zajęć dla tego uczestnika.',
            ], 404);
        }

        if ($event->eventDate < now()->toDateString()) {
            return response()->json([
                'success' => false,
                'error' => 'EVENT_IN_PAST',
                'message' => 'Nie można zgłosić nieobecności na zajęcia, które już się odbyły.',
            ], 422);
        }

        $existing = AbsenceReport::where('participant_user_id', $participantId)
            ->where('schedules_events_settlements_id', (int) $validated['schedulesEventsSettlementsID'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'alreadyReported' => true,
                'message' => 'Nieobecność na te zajęcia została już zgłoszona.',
                'data' => ['id' => $existing->id],
            ]);
        }

        $report = AbsenceReport::create([
            'reporter_user_id' => (int) $authUser->UsersID,
            'participant_user_id' => $participantId,
            'schedules_events_settlements_id' => (int) $validated['schedulesEventsSettlementsID'],
            'event_date' => $event->eventDate,
            'time_from' => substr((string) $event->timeFrom, 0, 5),
            'time_to' => substr((string) $event->timeTo, 0, 5),
            'course_title' => (string) $event->title,
            'reason' => $validated['reason'] ?? null,
            'status' => 'reported',
        ]);

        OutboxEvent::create([
            'entity' => 'absence_reports',
            'action' => 'created',
            'local_id' => $report->id,
            'payload' => [
                'reporterUsersID' => (int) $authUser->UsersID,
                'participantUsersID' => $participantId,
                'schedulesEventsSettlementsID' => (int) $validated['schedulesEventsSettlementsID'],
                'eventDate' => (string) $event->eventDate,
                'timeFrom' => substr((string) $event->timeFrom, 0, 5),
                'timeTo' => substr((string) $event->timeTo, 0, 5),
                'reason' => $validated['reason'] ?? '',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        Log::info('Absence reported', [
            'report_id' => $report->id,
            'participant' => $participantId,
            'event' => (int) $validated['schedulesEventsSettlementsID'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nieobecność została zgłoszona. Nauczyciel i grupa zostaną poinformowani.',
            'data' => ['id' => $report->id],
        ], 201);
    }

    /**
     * Lista zgłoszonych nieobecności dla rodzica i osób powiązanych —
     * UI oznacza zajęcia, na które nieobecność już zgłoszono.
     */
    public function index(Request $request, string $parentGuid): JsonResponse
    {
        $authUser = $request->user();

        $parentUser = CrmUser::query()
            ->where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$parentUser || (int) $parentUser->UsersID !== (int) $authUser->UsersID) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $userIds = $this->familyUserIds((int) $authUser->UsersID);

        $reports = AbsenceReport::whereIn('participant_user_id', $userIds)
            ->where('status', '!=', 'cancelled')
            ->where('event_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('event_date')
            ->get()
            ->map(static function (AbsenceReport $report) {
                return [
                    'id' => $report->id,
                    'participantUsersID' => (int) $report->participant_user_id,
                    'schedulesEventsSettlementsID' => (int) $report->schedules_events_settlements_id,
                    'eventDate' => $report->event_date?->format('Y-m-d'),
                    'timeFrom' => (string) $report->time_from,
                    'timeTo' => (string) $report->time_to,
                    'courseTitle' => (string) $report->course_title,
                    'status' => (string) $report->status,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'body' => $reports,
        ]);
    }

    /**
     * Zalogowany użytkownik może działać w imieniu swoim lub osoby powiązanej
     * (usersrelations, Parent_UsersID = auth, Cancelled = 0).
     */
    private function canActFor(CrmUser $authUser, int $participantId): bool
    {
        if ((int) $authUser->UsersID === $participantId) {
            return true;
        }

        return in_array($participantId, $this->familyUserIds((int) $authUser->UsersID), true);
    }

    private function familyUserIds(int $parentUsersId): array
    {
        $relatedIds = DB::table('usersrelations as ur')
            ->join('users as u', function ($join) {
                $join->on('u.UsersID', '=', 'ur.UsersID')
                    ->where('u.Cancelled', '=', 0);
            })
            ->where('ur.Parent_UsersID', $parentUsersId)
            ->where('ur.Cancelled', 0)
            ->pluck('u.UsersID')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $relatedIds[] = $parentUsersId;

        return array_values(array_unique($relatedIds));
    }
}
