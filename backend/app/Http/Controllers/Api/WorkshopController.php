<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkshopYgm;
use App\Models\WorkshopEuropean;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    // ─────────────────────────────────────────────
    // YGM Workshops
    // ─────────────────────────────────────────────

    /**
     * GET /api/offers/workshops/ygm
     */
    public function indexYgm(Request $request)
    {
        $query = WorkshopYgm::query()->where('website_status_id', '!=', 0);
        $this->applyCommonFilters($query, $request);

        $items = $query->orderBy('starts_at')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $items->map(fn ($w) => $this->mapWorkshop($w)),
            'recordCount' => $items->count(),
        ]);
    }

    /**
     * GET /api/offers/workshops/ygm/{id}
     */
    public function showYgm(int $id, \App\Services\CourseHeadingPricingService $pricingService)
    {
        $workshop = WorkshopYgm::where('crm_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $mapped = $this->mapWorkshop($workshop);

        $pricing = [];
        if ($workshop->courses_headings_id) {
            $startDate = $workshop->starts_at ? $workshop->starts_at->toDateString() : now()->toDateString();
            $pricing = $pricingService->getPriceByCourseHeadingsID(
                (int) $workshop->courses_headings_id,
                $startDate,
                $workshop->products_id ? (int) $workshop->products_id : null
            );
        }

        $mapped['prices'] = $pricing;
        $mapped['terms']  = [
            [
                'id'        => $workshop->crm_id,
                'name'      => $workshop->title,
                'startDate' => $workshop->starts_at?->toDateString(),
                'endDate'   => $workshop->ends_at?->toDateString(),
            ]
        ];

        return response()->json([
            'status' => '200',
            'body'   => $mapped,
        ]);
    }

    // ─────────────────────────────────────────────
    // European Workshops
    // ─────────────────────────────────────────────

    /**
     * GET /api/offers/workshops/european
     */
    public function indexEuropean(Request $request)
    {
        $query = WorkshopEuropean::query()->where('website_status_id', '!=', 0);
        $this->applyCommonFilters($query, $request);

        $items = $query->orderBy('starts_at')->get();

        return response()->json([
            'status'      => '200',
            'body'        => $items->map(fn ($w) => $this->mapWorkshop($w)),
            'recordCount' => $items->count(),
        ]);
    }

    /**
     * GET /api/offers/workshops/european/{id}
     */
    public function showEuropean(int $id, \App\Services\CourseHeadingPricingService $pricingService)
    {
        $workshop = WorkshopEuropean::where('crm_id', $id)
            ->orWhere('id', $id)
            ->firstOrFail();

        $mapped = $this->mapWorkshop($workshop);

        $pricing = [];
        if ($workshop->courses_headings_id) {
            $startDate = $workshop->starts_at ? $workshop->starts_at->toDateString() : now()->toDateString();
            $pricing = $pricingService->getPriceByCourseHeadingsID(
                (int) $workshop->courses_headings_id,
                $startDate,
                $workshop->products_id ? (int) $workshop->products_id : null
            );
        }

        $mapped['prices'] = $pricing;
        $mapped['terms']  = [
            [
                'id'        => $workshop->crm_id,
                'name'      => $workshop->title,
                'startDate' => $workshop->starts_at?->toDateString(),
                'endDate'   => $workshop->ends_at?->toDateString(),
            ]
        ];

        return response()->json([
            'status' => '200',
            'body'   => $mapped,
        ]);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function applyCommonFilters($query, Request $request): void
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

    private function mapWorkshop($workshop): array
    {
        return [
            'id'               => $workshop->crm_id,
            'title'            => $workshop->title,
            'description'      => $workshop->description,
            'offerType'        => $workshop->offer_type,
            'websiteStatusId'  => $workshop->website_status_id,
            'isClosed'         => (bool) $workshop->is_closed,
            'startsAt'         => $workshop->starts_at?->toDateString(),
            'endsAt'           => $workshop->ends_at?->toDateString(),
            'localizationId'   => $workshop->localization_id,
            'localizationName' => $workshop->localization_name,
            'ageRangeId'       => $workshop->age_range_id,
            'ageRangeName'     => $workshop->age_range_name,
            'categoryId'       => $workshop->category_id,
            'categoryName'     => $workshop->category_name,
            'levelId'          => $workshop->level_id,
            'levelName'        => $workshop->level_name,
            'styleId'          => $workshop->style_id,
            'styleName'        => $workshop->style_name,
            'instructors'      => $workshop->instructors,
            'nextEventDate'    => $workshop->next_event_date?->toDateString(),
            'startTime'        => $workshop->start_time,
            'availablePlaces'  => $workshop->available_places,
            'capacity'         => $workshop->capacity,
            'workshopType'     => $workshop->workshop_type,
            'groupId'          => $workshop->group_id,
            'workshopLevel'    => $workshop->workshop_level,
            'enrollmentMode'   => $workshop->enrollment_mode,
            'coursesHeadingsId' => $workshop->courses_headings_id,
            'productsId'       => $workshop->products_id,
        ];
    }
}
