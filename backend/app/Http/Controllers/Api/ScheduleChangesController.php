<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleChangesController extends Controller
{
    public function getMissedLessons(Request $request, string $parentGuid): JsonResponse
    {
        [$authUser, $parentUser, $relatedUsers] = $this->resolveParentContext($request, $parentGuid);

        if (!$authUser || !$parentUser) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
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
            return response()->json(['success' => true, 'body' => []]);
        }

        $placeholders = implode(',', array_fill(0, count($scopeUserIds), '?'));

        $rows = DB::select(
            "
            SELECT
                us.usersschedulesid,
                us.usersid,
                us.usersproductsid,
                us.coursesheadingsid,
                us.attendancestatusdvid,
                us.attendancetypesdvid,
                us.attended,
                us.scheduleseventssettlementsid,
                ses.eventDate,
                ses.timeFrom,
                ses.timeTo,
                COALESCE(ch.CourseHeadingName, xs.groupname, '') AS title,
                COALESCE(xs.groupname, '') AS groupName,
                COALESCE(l.LocalizationName, xs.location, course.localizationName, '') AS locationName,
                COALESCE(xs.instructors, course.instructorsList, '') AS instructors,
                u.FirstName AS participantFirstName,
                u.LastName AS participantLastName,
                d_status.Name AS attendanceStatusName,
                d_type.Name AS attendanceTypeName
            FROM usersschedules us
            INNER JOIN users u
                ON u.UsersID = us.usersid
                AND u.Cancelled = 0
            INNER JOIN scheduleseventssettlements ses
                ON ses.schedulesEventsSettlementsID = us.scheduleseventssettlementsid
                AND ses.cancelled = 0
            LEFT JOIN xschedules xs
                ON xs.id = ses.schedulesID
                AND xs.cancelled = 0
            LEFT JOIN coursesheadings ch
                ON ch.CoursesHeadingsID = us.coursesheadingsid
                AND ch.Cancelled = 0
            LEFT JOIN courses course
                ON course.coursesHeadingsID = us.coursesheadingsid
            LEFT JOIN localizations l
                ON l.LocalizationsID = ses.localizationsID
                AND l.Cancelled = 0
            LEFT JOIN dictionaries d_status
                ON d_status.DictionaryName = 'attendanceStatus'
                AND d_status.ValueID = us.attendancestatusdvid
                AND d_status.Cancelled = 0
            LEFT JOIN dictionaries d_type
                ON d_type.DictionaryName = 'AttendanceTypes'
                AND d_type.ValueID = us.attendancetypesdvid
                AND d_type.Cancelled = 0
            LEFT JOIN usersschedules us_workoff
                ON us_workoff.workoff_scheduleseventssettlementsid = us.scheduleseventssettlementsid
                AND us_workoff.usersid = us.usersid
                AND us_workoff.cancelled = 0
                AND us_workoff.attendancetypesdvid = 3
            WHERE us.cancelled = 0
                AND us.usersid IN ($placeholders)
                AND us.attendancestatusdvid = 2
                AND us_workoff.usersschedulesid IS NULL
            ORDER BY ses.eventDate DESC, ses.timeFrom DESC, u.LastName ASC, u.FirstName ASC
            ",
            $scopeUserIds
        );

        return response()->json([
            'success' => true,
            'body' => array_map([$this, 'mapMissedLessonRow'], $rows),
        ]);
    }

    public function getWorkoffLessons(Request $request, string $parentGuid): JsonResponse
    {
        [$authUser, $parentUser, $relatedUsers] = $this->resolveParentContext($request, $parentGuid);

        if (!$authUser || !$parentUser) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN'], 403);
        }

        $validated = $request->validate([
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
            return response()->json(['success' => true, 'body' => []]);
        }

        $placeholders = implode(',', array_fill(0, count($scopeUserIds), '?'));

        $rows = DB::select(
            "
            SELECT
                us.usersschedulesid,
                us.usersid,
                us.usersproductsid,
                us.coursesheadingsid,
                us.attendancestatusdvid,
                us.attendancetypesdvid,
                us.attended,
                us.scheduleseventssettlementsid,
                ses_makeup.eventDate AS makeupEventDate,
                ses_makeup.timeFrom AS makeupTimeFrom,
                ses_makeup.timeTo AS makeupTimeTo,
                COALESCE(ch_makeup.CourseHeadingName, xs_makeup.groupname, '') AS makeupTitle,
                COALESCE(xs_makeup.groupname, '') AS makeupGroupName,
                COALESCE(l_makeup.LocalizationName, xs_makeup.location, course_makeup.localizationName, '') AS makeupLocationName,
                COALESCE(xs_makeup.instructors, course_makeup.instructorsList, '') AS makeupInstructors,
                us.workoff_coursesheadingsid,
                COALESCE(
                    us.workoff_eventdate,
                    ses_origin.eventDate
                ) AS originalEventDate,
                COALESCE(
                    us.workoff_timefrom,
                    ses_origin.timeFrom
                ) AS originalTimeFrom,
                COALESCE(
                    us.workoff_timeto,
                    ses_origin.timeTo
                ) AS originalTimeTo,
                COALESCE(ch_origin.CourseHeadingName, '') AS originalTitle,
                u.FirstName AS participantFirstName,
                u.LastName AS participantLastName,
                d_status.Name AS attendanceStatusName,
                d_type.Name AS attendanceTypeName
            FROM usersschedules us
            INNER JOIN users u
                ON u.UsersID = us.usersid
                AND u.Cancelled = 0
            INNER JOIN scheduleseventssettlements ses_makeup
                ON ses_makeup.schedulesEventsSettlementsID = us.scheduleseventssettlementsid
                AND ses_makeup.cancelled = 0
            LEFT JOIN scheduleseventssettlements ses_origin
                ON ses_origin.schedulesEventsSettlementsID = us.workoff_scheduleseventssettlementsid
                AND ses_origin.cancelled = 0
            LEFT JOIN xschedules xs_makeup
                ON xs_makeup.id = ses_makeup.schedulesID
                AND xs_makeup.cancelled = 0
            LEFT JOIN coursesheadings ch_makeup
                ON ch_makeup.CoursesHeadingsID = us.coursesheadingsid
                AND ch_makeup.Cancelled = 0
            LEFT JOIN courses course_makeup
                ON course_makeup.coursesHeadingsID = us.coursesheadingsid
            LEFT JOIN coursesheadings ch_origin
                ON ch_origin.CoursesHeadingsID = us.workoff_coursesheadingsid
                AND ch_origin.Cancelled = 0
            LEFT JOIN localizations l_makeup
                ON l_makeup.LocalizationsID = ses_makeup.localizationsID
                AND l_makeup.Cancelled = 0
            LEFT JOIN dictionaries d_status
                ON d_status.DictionaryName = 'attendanceStatus'
                AND d_status.ValueID = us.attendancestatusdvid
                AND d_status.Cancelled = 0
            LEFT JOIN dictionaries d_type
                ON d_type.DictionaryName = 'AttendanceTypes'
                AND d_type.ValueID = us.attendancetypesdvid
                AND d_type.Cancelled = 0
            WHERE us.cancelled = 0
                AND us.usersid IN ($placeholders)
                AND us.attendancetypesdvid = 3
            ORDER BY ses_makeup.eventDate DESC, ses_makeup.timeFrom DESC, u.LastName ASC, u.FirstName ASC
            ",
            $scopeUserIds
        );

        return response()->json([
            'success' => true,
            'body' => array_map([$this, 'mapWorkoffLessonRow'], $rows),
        ]);
    }

    private function mapMissedLessonRow(object $row): array
    {
        return [
            'id' => (int) $row->usersschedulesid,
            'usersID' => (int) $row->usersid,
            'usersProductsID' => (int) $row->usersproductsid,
            'coursesHeadingsID' => (int) $row->coursesheadingsid,
            'attendanceStatusDVID' => (int) $row->attendancestatusdvid,
            'attendanceTypesDVID' => (int) $row->attendancetypesdvid,
            'attended' => isset($row->attended) ? (int) $row->attended : null,
            'schedulesEventsSettlementsID' => (int) $row->scheduleseventssettlementsid,
            'eventDate' => $row->eventDate,
            'timeFrom' => substr((string) $row->timeFrom, 0, 5),
            'timeTo' => substr((string) $row->timeTo, 0, 5),
            'title' => (string) $row->title,
            'groupName' => (string) $row->groupName,
            'locationName' => (string) $row->locationName,
            'instructors' => (string) $row->instructors,
            'participantFirstName' => (string) $row->participantFirstName,
            'participantLastName' => (string) $row->participantLastName,
            'participantFullName' => trim(((string) $row->participantFirstName) . ' ' . ((string) $row->participantLastName)),
            'attendanceStatusName' => (string) ($row->attendanceStatusName ?? ''),
            'attendanceTypeName' => (string) ($row->attendanceTypeName ?? ''),
        ];
    }

    private function mapWorkoffLessonRow(object $row): array
    {
        return [
            'id' => (int) $row->usersschedulesid,
            'usersID' => (int) $row->usersid,
            'usersProductsID' => (int) $row->usersproductsid,
            'coursesHeadingsID' => (int) $row->coursesheadingsid,
            'attendanceStatusDVID' => (int) $row->attendancestatusdvid,
            'attendanceTypesDVID' => (int) $row->attendancetypesdvid,
            'attended' => isset($row->attended) ? (int) $row->attended : null,
            'schedulesEventsSettlementsID' => (int) $row->scheduleseventssettlementsid,
            'makeupEventDate' => $row->makeupEventDate,
            'makeupTimeFrom' => substr((string) $row->makeupTimeFrom, 0, 5),
            'makeupTimeTo' => substr((string) $row->makeupTimeTo, 0, 5),
            'makeupTitle' => (string) $row->makeupTitle,
            'makeupGroupName' => (string) $row->makeupGroupName,
            'makeupLocationName' => (string) $row->makeupLocationName,
            'makeupInstructors' => (string) $row->makeupInstructors,
            'originalCoursesHeadingsID' => (int) $row->workoff_coursesheadingsid,
            'originalEventDate' => $row->originalEventDate,
            'originalTimeFrom' => $row->originalTimeFrom ? substr((string) $row->originalTimeFrom, 0, 5) : '',
            'originalTimeTo' => $row->originalTimeTo ? substr((string) $row->originalTimeTo, 0, 5) : '',
            'originalTitle' => (string) $row->originalTitle,
            'participantFirstName' => (string) $row->participantFirstName,
            'participantLastName' => (string) $row->participantLastName,
            'participantFullName' => trim(((string) $row->participantFirstName) . ' ' . ((string) $row->participantLastName)),
            'attendanceStatusName' => (string) ($row->attendanceStatusName ?? ''),
            'attendanceTypeName' => (string) ($row->attendanceTypeName ?? ''),
        ];
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

        // Własne ID zalogowanego ZAWSZE w zakresie — jak w CalendarController.
        // CRM tworzy relacje odwrotne (Parent_UsersID = dziecko -> rodzic),
        // więc bez self konto dziecka dostawało scope = [ID rodzica] i puste
        // listy nieobecności/odrabiania.
        return array_values(array_unique(array_merge(
            [(int) $authUser->UsersID],
            $relatedIds
        )));
    }
}
