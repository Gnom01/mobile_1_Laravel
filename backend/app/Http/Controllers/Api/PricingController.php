<?php

namespace App\Http\Controllers\Api;

use App\Services\CourseHeadingPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PricingController extends Controller
{
    private CourseHeadingPricingService $pricingService;

    public function __construct(CourseHeadingPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * POST /api/GetPriceByCourseHeadingsID
     *
     * Body (JSON):
     *   coursesHeadingsID      (required)  int
     *   startDate              (required)  Y-m-d
     *   current_LocalizationsID            int|string  (passed through, future use)
     *   promotionsSalesIDList              mixed       (passed through, future use)
     *   productsID                         int         (optional filter)
     */
    public function getPrice(Request $request): JsonResponse
    {
        $request->validate([
            'coursesHeadingsID' => ['required', 'integer'],
            'startDate'         => ['required', 'date_format:Y-m-d'],
            'productsID'        => ['sometimes', 'nullable', 'integer'],
        ]);

        $coursesHeadingsID = (int) $request->input('coursesHeadingsID');
        $startDate         = $request->input('startDate');
        $productsID        = $request->filled('productsID')
            ? (int) $request->input('productsID')
            : null;

        $result = $this->pricingService->getPriceByCourseHeadingsID(
            $coursesHeadingsID,
            $startDate,
            $productsID
        );

        return response()->json($result);
    }

    /**
     * GET /pricing/course/{coursesHeadingsID}
     *
     * Query params:
     *   startDate    (required)  Y-m-d
     *   products_id  (optional)  int
     */
    public function getPriceByCourseHeadingsID(Request $request, int $coursesHeadingsID): JsonResponse
    {
        $request->validate([
            'startDate'   => ['required', 'date_format:Y-m-d'],
            'products_id' => ['sometimes', 'integer'],
        ]);

        $startDate  = $request->input('startDate');
        $productsID = $request->has('products_id')
            ? (int) $request->input('products_id')
            : null;

        $result = $this->pricingService->getPriceByCourseHeadingsID(
            $coursesHeadingsID,
            $startDate,
            $productsID
        );

        return response()->json($result);
    }

    /**
     * GET /api/pricing/entry-fee?localizationsID=99
     *
     * Checks whether an entry fee product should be offered to the authenticated user.
     *
     * Response:
     *   { "entryFeeRequired": false }                   → user already paid
     *   { "entryFeeRequired": true, "product": {...} }  → product to offer
     *   { "entryFeeRequired": false, "product": null, "message": "..." } → not configured
     */
    public function checkEntryFee(Request $request): JsonResponse
    {
        $request->validate([
            'localizationsID' => ['required', 'integer'],
        ]);

        $localizationsID = (int) $request->input('localizationsID');

        /** @var \App\Models\CrmUser $crmUser */
        $crmUser = $request->user();

        if (!$crmUser) {
            return response()->json(['message' => 'CRM user not found.'], 404);
        }

        $result = $this->pricingService->checkForEntryFee(
            (int) $crmUser->UsersID,
            $localizationsID
        );

        if ($result === false) {
            return response()->json(['entryFeeRequired' => false]);
        }

        if ($result === null) {
            return response()->json([
                'entryFeeRequired' => false,
                'product'          => null,
                'message'          => 'Brak skonfigurowanej opłaty wpisowej w systemie.',
            ]);
        }

        return response()->json([
            'entryFeeRequired' => true,
            'product'          => $result,
        ]);
    }
}