<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Search/filter courses by optional parameters sent by the mobile app.
     *
     * POST /courses/search
     *
     * Body (all optional):
     *   localizationID  int  — filter by localizationsID
     *   ageRangeID      int  — filter by courseAgeRangesDVID
     *   danceStyleID    int  — filter by courseDanceStyleDID
     *   levelID         int  — filter by courseLevelDID
     *   weekDayID       int  — filter by startWeekDaysDVID
     */
    public function search(Request $request)
    {
        // Ta sama brama dostępności co portal: sprzedawalne są wyłącznie
        // statusy WWW 2 i 3 (portal sprawdza to w 7 miejscach przy każdym
        // wejściu; '!=0' przepuszczał kursy niedostępne do sprzedaży).
        $query = Course::query()
            ->whereIn('websiteStatusesDVID', [2, 3])
            ->where('cancelled', 0)
            ->with('prices');

        if ($request->filled('localizationID')) {
            $query->where('localizationsID', (int) $request->input('localizationID'));
        }

        if ($request->filled('ageRangeID')) {
            $query->where('courseAgeRangesDVID', (int) $request->input('ageRangeID'));
        }

        if ($request->filled('danceStyleID')) {
            $query->where('courseDanceStyleDID', (int) $request->input('danceStyleID'));
        }

        if ($request->filled('levelID')) {
            $query->where('courseLevelDID', (int) $request->input('levelID'));
        }

        if ($request->filled('weekDayID')) {
            $query->where('startWeekDaysDVID', (int) $request->input('weekDayID'));
        }

        // Filtr instruktora — portal zawęża po instructorEmployeesIDList;
        // wcześniej Flutter wysyłał employeeID, a backend go po cichu ignorował.
        if ($request->filled('employeeID')) {
            $employeeId = (int) $request->input('employeeID');
            $query->whereRaw(
                'FIND_IN_SET(?, REPLACE(instructorEmployeesIDList, " ", ""))',
                [$employeeId]
            );
        }

        $courses = $query->orderBy('courseHeadingName')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $courses,
            'recordCount' => $courses->count(),
        ]);
    }
}
