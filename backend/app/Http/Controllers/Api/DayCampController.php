<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DayCamp;
use Illuminate\Http\Request;

class DayCampController extends Controller
{
    /**
     * GET /api/offers/day-camps
     */
    public function index(Request $request)
    {
        $query = DayCamp::query()->where('website_status_id', '!=', 0);
        $this->applyFilters($query, $request);

        $items = $query->orderBy('starts_at')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $items->map(fn ($c) => $this->mapDayCamp($c)),
            'recordCount' => $items->count(),
        ]);
    }

    /**
     * GET /api/offers/day-camps/{id}
     */
    public function show(int $id)
    {
        $dayCamp = DayCamp::where('crm_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => '200',
            'body'   => $this->mapDayCamp($dayCamp),
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('localizationID')) {
            $query->where('localization_id', (int) $request->input('localizationID'));
        }
        if ($request->filled('ageRangeID')) {
            $query->where('age_range_id', (int) $request->input('ageRangeID'));
        }
        if ($request->filled('categoryID')) {
            $query->where('category_id', (int) $request->input('categoryID'));
        }
        if ($request->filled('levelID')) {
            $query->where('level_id', (int) $request->input('levelID'));
        }
        if ($request->filled('styleID')) {
            $query->where('style_id', (int) $request->input('styleID'));
        }
        if ($request->filled('dateFrom')) {
            $query->where('starts_at', '>=', $request->input('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->where('ends_at', '<=', $request->input('dateTo'));
        }
        if ($request->filled('offerType')) {
            $query->where('offer_type', $request->input('offerType'));
        }
        if ($request->boolean('availableOnly')) {
            $query->where('is_closed', 0)->where('available_places', '>', 0);
        }
    }

    private function mapDayCamp(DayCamp $dayCamp): array
    {
        return [
            'id'               => $dayCamp->crm_id,
            'title'            => $dayCamp->title,
            'description'      => $dayCamp->description,
            'offerType'        => $dayCamp->offer_type,
            'websiteStatusId'  => $dayCamp->website_status_id,
            'isClosed'         => (bool) $dayCamp->is_closed,
            'startsAt'         => $dayCamp->starts_at?->toDateString(),
            'endsAt'           => $dayCamp->ends_at?->toDateString(),
            'localizationId'   => $dayCamp->localization_id,
            'localizationName' => $dayCamp->localization_name,
            'ageRangeId'       => $dayCamp->age_range_id,
            'ageRangeName'     => $dayCamp->age_range_name,
            'categoryId'       => $dayCamp->category_id,
            'categoryName'     => $dayCamp->category_name,
            'levelId'          => $dayCamp->level_id,
            'levelName'        => $dayCamp->level_name,
            'styleId'          => $dayCamp->style_id,
            'styleName'        => $dayCamp->style_name,
            'instructors'      => $dayCamp->instructors,
            'nextEventDate'    => $dayCamp->next_event_date?->toDateString(),
            'startTime'        => $dayCamp->start_time,
            'availablePlaces'  => $dayCamp->available_places,
            'capacity'         => $dayCamp->capacity,
            'turnusName'       => $dayCamp->turnus_name,
            'departurePlace'   => $dayCamp->departure_place,
            'transportOptions' => $dayCamp->transport_options,
            'dietOptions'      => $dayCamp->diet_options,
            'medicalRequired'  => (bool) $dayCamp->medical_required,
            'guardianRequired' => (bool) $dayCamp->guardian_required,
            'coursesHeadingsId' => $dayCamp->courses_headings_id,
            'productsId'       => $dayCamp->products_id,
        ];
    }
}
