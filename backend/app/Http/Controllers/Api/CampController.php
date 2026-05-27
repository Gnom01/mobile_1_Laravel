<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use Illuminate\Http\Request;

class CampController extends Controller
{
    /**
     * GET /api/offers/camps
     */
    public function index(Request $request)
    {
        $query = Camp::query()->where('website_status_id', '!=', 0);
        $this->applyFilters($query, $request);

        $items = $query->orderBy('starts_at')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $items->map(fn ($c) => $this->mapCamp($c)),
            'recordCount' => $items->count(),
        ]);
    }

    /**
     * GET /api/offers/camps/{id}
     */
    public function show(int $id, \App\Services\CourseHeadingPricingService $pricingService)
    {
        $camp = Camp::where('crm_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $mapped = $this->mapCamp($camp);

        $pricing = [];
        if ($camp->courses_headings_id) {
            $startDate = $camp->starts_at ? $camp->starts_at->toDateString() : now()->toDateString();
            $pricing = $pricingService->getPriceByCourseHeadingsID(
                (int) $camp->courses_headings_id,
                $startDate,
                $camp->products_id ? (int) $camp->products_id : null
            );
        }

        $mapped['prices'] = $pricing;
        $mapped['terms']  = [
            [
                'id'        => $camp->crm_id,
                'name'      => $camp->turnus_name ?: $camp->title,
                'startDate' => $camp->starts_at?->toDateString(),
                'endDate'   => $camp->ends_at?->toDateString(),
            ]
        ];

        return response()->json([
            'status' => '200',
            'body'   => $mapped,
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

    private function mapCamp(Camp $camp): array
    {
        return [
            'id'               => $camp->crm_id,
            'title'            => $camp->title,
            'description'      => $camp->description,
            'offerType'        => $camp->offer_type,
            'websiteStatusId'  => $camp->website_status_id,
            'isClosed'         => (bool) $camp->is_closed,
            'startsAt'         => $camp->starts_at?->toDateString(),
            'endsAt'           => $camp->ends_at?->toDateString(),
            'localizationId'   => $camp->localization_id,
            'localizationName' => $camp->localization_name,
            'ageRangeId'       => $camp->age_range_id,
            'ageRangeName'     => $camp->age_range_name,
            'categoryId'       => $camp->category_id,
            'categoryName'     => $camp->category_name,
            'levelId'          => $camp->level_id,
            'levelName'        => $camp->level_name,
            'styleId'          => $camp->style_id,
            'styleName'        => $camp->style_name,
            'instructors'      => $camp->instructors,
            'nextEventDate'    => $camp->next_event_date?->toDateString(),
            'startTime'        => $camp->start_time,
            'availablePlaces'  => $camp->available_places,
            'capacity'         => $camp->capacity,
            'turnusName'       => $camp->turnus_name,
            'departurePlace'   => $camp->departure_place,
            'transportOptions' => $camp->transport_options,
            'dietOptions'      => $camp->diet_options,
            'medicalRequired'  => (bool) $camp->medical_required,
            'guardianRequired' => (bool) $camp->guardian_required,
            'coursesHeadingsId' => $camp->courses_headings_id,
            'productsId'       => $camp->products_id,
        ];
    }
}
