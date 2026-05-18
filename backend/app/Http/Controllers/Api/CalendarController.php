<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function getPeople(Request $request, string $parentGuid): JsonResponse
    {
        [$authUser, $parentUser, $relatedUsers] = $this->resolveParentContext($request, $parentGuid);

        if (!$authUser || !$parentUser) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        return response()->json([
            'success' => true,
            'body' => $relatedUsers->map(function ($user) {
                return [
                    'guid' => (string) ($user->guid ?? ''),
                    'firstName' => (string) ($user->FirstName ?? ''),
                    'lastName' => (string) ($user->LastName ?? ''),
                    'fullName' => trim((string) ($user->fullName ?? '')),
                ];
            })->values(),
        ]);
    }

    public function getMonthSummary(Request $request, string $parentGuid): JsonResponse
    {
        [$authUser, $parentUser, $relatedUsers] = $this->resolveParentContext($request, $parentGuid);

        if (!$authUser || !$parentUser) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'personGuid' => ['nullable', 'string'],
        ]);

        $scopeUserIds = $this->resolveScopeUserIds(
            $authUser,
            $parentUser,
            $relatedUsers,
            $validated['personGuid'] ?? null
        );

        if ($scopeUserIds === null) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        if (empty($scopeUserIds)) {
            return response()->json([
                'success' => true,
                'body' => [],
            ]);
        }

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $placeholders = implode(',', array_fill(0, count($scopeUserIds), '?'));

        $rows = DB::select(
            "
            SELECT
                ses.eventDate AS date,
                COUNT(DISTINCT CONCAT(c.usersID, '-', ses.schedulesEventsSettlementsID)) AS eventsCount
            FROM contracts c
            INNER JOIN scheduleseventssettlements ses
                ON ses.coursesHeadingsID = c.coursesHeadingsID
                AND ses.cancelled = 0
            WHERE c.cancelled = 0
                AND c.usersID IN ($placeholders)
                AND ses.eventDate BETWEEN ? AND ?
                AND (c.contractPeriodFrom IS NULL OR c.contractPeriodFrom <= ?)
                AND (c.contractPeriodTo IS NULL OR c.contractPeriodTo >= ?)
            GROUP BY ses.eventDate
            ORDER BY ses.eventDate ASC
            ",
            array_merge($scopeUserIds, [$monthStart, $monthEnd, $monthEnd, $monthStart])
        );

        $body = array_map(static function ($row) {
            return [
                'date' => $row->date,
                'count' => (int) $row->eventsCount,
            ];
        }, $rows);

        return response()->json([
            'success' => true,
            'body' => $body,
        ]);
    }

    public function getDayEvents(Request $request, string $parentGuid): JsonResponse
    {
        [$authUser, $parentUser, $relatedUsers] = $this->resolveParentContext($request, $parentGuid);

        if (!$authUser || !$parentUser) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'personGuid' => ['nullable', 'string'],
        ]);

        $scopeUserIds = $this->resolveScopeUserIds(
            $authUser,
            $parentUser,
            $relatedUsers,
            $validated['personGuid'] ?? null
        );

        if ($scopeUserIds === null) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        if (empty($scopeUserIds)) {
            return response()->json([
                'success' => true,
                'body' => [],
            ]);
        }

        $selectedDate = $validated['date'];
        $placeholders = implode(',', array_fill(0, count($scopeUserIds), '?'));

        $rows = DB::select(
            "
            SELECT DISTINCT
                ses.schedulesEventsSettlementsID,
                ses.schedulesID,
                ses.eventDate,
                ses.timeFrom,
                ses.timeTo,
                ses.coursesHeadingsID,
                c.usersID AS participantUsersID,
                c.userFirstName AS participantFirstName,
                c.userLastName AS participantLastName,
                COALESCE(course.courseHeadingName, ch.CourseHeadingName, c.courseHeadingName, '') AS title,
                COALESCE(xs.groupname, c.courseHeadingName, course.courseHeadingName, ch.CourseHeadingName, '') AS groupName,
                COALESCE(l.LocalizationName, course.localizationName, xs.location, '') AS locationName,
                COALESCE(xs.instructors, course.instructorsList, '') AS instructors,
                COALESCE(xs.description, '') AS description
            FROM contracts c
            INNER JOIN scheduleseventssettlements ses
                ON ses.coursesHeadingsID = c.coursesHeadingsID
                AND ses.cancelled = 0
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
            WHERE c.cancelled = 0
                AND c.usersID IN ($placeholders)
                AND ses.eventDate = ?
                AND (c.contractPeriodFrom IS NULL OR c.contractPeriodFrom <= ?)
                AND (c.contractPeriodTo IS NULL OR c.contractPeriodTo >= ?)
            ORDER BY ses.timeFrom ASC, ses.timeTo ASC, c.userLastName ASC, c.userFirstName ASC
            ",
            array_merge($scopeUserIds, [$selectedDate, $selectedDate, $selectedDate])
        );

        $body = array_map(static function ($row) {
            return [
                'id' => (int) $row->schedulesEventsSettlementsID,
                'scheduleId' => (int) $row->schedulesID,
                'date' => $row->eventDate,
                'timeFrom' => substr((string) $row->timeFrom, 0, 5),
                'timeTo' => substr((string) $row->timeTo, 0, 5),
                'coursesHeadingsID' => (int) $row->coursesHeadingsID,
                'participantUsersID' => (int) $row->participantUsersID,
                'participantFirstName' => (string) $row->participantFirstName,
                'participantLastName' => (string) $row->participantLastName,
                'participantFullName' => trim(
                    sprintf(
                        '%s %s',
                        (string) $row->participantFirstName,
                        (string) $row->participantLastName
                    )
                ),
                'title' => (string) $row->title,
                'groupName' => (string) $row->groupName,
                'locationName' => (string) $row->locationName,
                'instructors' => (string) $row->instructors,
                'description' => (string) $row->description,
            ];
        }, $rows);

        return response()->json([
            'success' => true,
            'body' => $body,
        ]);
    }

    private function resolveParentContext(Request $request, string $parentGuid): array
    {
        $authUser = $request->user();

        if (!$authUser) {
            return [null, null, collect()];
        }

        $parentUser = CrmUser::query()
            ->where('guid', $parentGuid)
            ->where('Cancelled', 0)
            ->first();

        if (!$parentUser || (int) $parentUser->UsersID !== (int) $authUser->UsersID) {
            return [$authUser, null, collect()];
        }

        $relatedUsers = $this->getRelatedUsers((int) $authUser->UsersID);

        return [$authUser, $parentUser, $relatedUsers];
    }

    private function getRelatedUsers(int $parentUsersId): Collection
    {
        return DB::table('usersrelations as ur')
            ->join('users as u', function ($join) {
                $join->on('u.UsersID', '=', 'ur.UsersID')
                    ->where('u.Cancelled', '=', 0);
            })
            ->where('ur.Parent_UsersID', $parentUsersId)
            ->where('ur.Cancelled', 0)
            ->select(
                'u.UsersID',
                'u.guid',
                'u.FirstName',
                'u.LastName',
                'u.fullName'
            )
            ->orderBy('u.LastName')
            ->orderBy('u.FirstName')
            ->get();
    }

    private function resolveScopeUserIds(
        CrmUser $authUser,
        CrmUser $parentUser,
        Collection $relatedUsers,
        ?string $personGuid
    ): ?array {
        if (!empty($personGuid)) {
            if ($personGuid === (string) $parentUser->guid) {
                return [(int) $authUser->UsersID];
            }

            $selected = $relatedUsers->firstWhere('guid', $personGuid);

            if (!$selected) {
                return null;
            }

            return [(int) $selected->UsersID];
        }

        $relatedIds = $relatedUsers
            ->pluck('UsersID')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!empty($relatedIds)) {
            return $relatedIds;
        }

        return [(int) $authUser->UsersID];
    }
}
