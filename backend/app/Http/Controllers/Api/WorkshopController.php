<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkshopYgm;
use App\Models\WorkshopEuropean;
use Illuminate\Http\Request;
use App\Services\CourseHeadingPricingService;
use App\Services\Order\CrmOrderClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $query = WorkshopYgm::query()
            ->where('website_status_id', '!=', 0)
            ->whereDate('ends_at', '>=', now()->toDateString());
        $this->withProductsLevel3Category($query);
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
        $query = WorkshopEuropean::query()
            ->where('website_status_id', '!=', 0)
            ->whereDate('ends_at', '>=', now()->toDateString());
        $this->withProductsLevel3Category($query);
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
            $this->whereProductsLevel3Category($query, (int) $request->input('categoryID'));
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

    private function withProductsLevel3Category($query): void
    {
        $table = $query->getModel()->getTable();

        $query->select("{$table}.*")->selectSub(function ($sub) use ($table) {
            $sub->from('coursesheadingsdimensions as chd')
                ->select('chd.positiondvid')
                ->whereColumn('chd.coursesheadingsid', "{$table}.courses_headings_id")
                ->where('chd.dictionaryname', 'ProductsLevel3')
                ->limit(1);
        }, 'products_level3_category_id');
    }

    private function whereProductsLevel3Category($query, int $categoryID): void
    {
        $table = $query->getModel()->getTable();

        $query->whereExists(function ($sub) use ($table, $categoryID) {
            $sub->select(DB::raw(1))
                ->from('coursesheadingsdimensions as chd')
                ->whereColumn('chd.coursesheadingsid', "{$table}.courses_headings_id")
                ->where('chd.dictionaryname', 'ProductsLevel3')
                ->where('chd.positiondvid', $categoryID);
        });
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
            'categoryId'       => $workshop->products_level3_category_id ?? $workshop->category_id,
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

    /**
     * POST /api/offers/workshops/calculate-pricing
     */
    public function calculatePricing(Request $request, CourseHeadingPricingService $pricingService)
    {
        $request->validate([
            'type'          => ['required', 'string', 'in:ygm,euro'],
            'participantID' => ['required', 'integer'],
            'categoryID'    => ['required', 'integer'],
        ]);

        $type = $request->input('type');
        $participantID = $request->input('participantID');
        $categoryID = $request->input('categoryID');

        if ($type === 'ygm') {
            if (!in_array($categoryID, [333, 334])) {
                return response()->json(['success' => false, 'message' => 'Nieprawidłowa kategoria dla YGM'], 400);
            }
            $workshops = WorkshopYgm::where('website_status_id', '!=', 0)
                ->whereDate('ends_at', '>=', now()->toDateString());
            $this->withProductsLevel3Category($workshops);
            $this->whereProductsLevel3Category($workshops, (int) $categoryID);
            $workshops = $workshops->get();
        } else {
            if ($categoryID !== 340) {
                return response()->json(['success' => false, 'message' => 'Nieprawidłowa kategoria dla Euro'], 400);
            }
            $workshops = WorkshopEuropean::where('website_status_id', '!=', 0)
                ->whereDate('ends_at', '>=', now()->toDateString());
            $this->withProductsLevel3Category($workshops);
            $this->whereProductsLevel3Category($workshops, (int) $categoryID);
            $workshops = $workshops->get();
        }

        $normalized = [];
        foreach ($workshops as $w) {
            $prices = $pricingService->getPriceByCourseHeadingsID(
                (int) $w->courses_headings_id,
                $w->starts_at ? $w->starts_at->toDateString() : now()->toDateString(),
                (int) $w->products_id
            );

            $priceInfo = count($prices) > 0 ? $prices[0] : null;

            $priceVal = $priceInfo ? (float) ($priceInfo['amount'] ?? 0) : 0.0;
            $fullPriceVal = $priceInfo ? (float) ($priceInfo['amount'] ?? 0) : 0.0;
            $priceListPositionName = $priceInfo ? ($priceInfo['priceListPositionName'] ?? '') : '';
            $priceListTemplateName = $priceInfo ? ($priceInfo['priceListTemplateName'] ?? '') : '';
            $pricelistPositionsTypesDVID = $priceInfo ? (int) ($priceInfo['pricelistPositionsTypesDVID'] ?? 6) : 6;
            
            // Get price list type
            $value = strtoupper($priceListPositionName . ' ' . $priceListTemplateName);
            $priceListType = 'PL';
            if (str_contains($value, 'EU') || str_contains($value, 'INT')) {
                $priceListType = 'EU';
            }

            // Duration
            $durationInMinutes = $priceInfo ? (int) ($priceInfo['durationInMinutes'] ?? 0) : 0;

            // Formatted Date
            $timeRange = $w->start_time;
            $timeParts = explode(' - ', $timeRange);
            $startTimeStr = count($timeParts) > 0 ? $timeParts[0] : '00:00';
            $endTimeStr = count($timeParts) > 1 ? $timeParts[1] : '23:59';

            $dateFromStr = $w->starts_at ? $w->starts_at->toDateString() . ' ' . $startTimeStr . ':00' : null;
            $dateToStr = $w->ends_at ? $w->ends_at->toDateString() . ' ' . $endTimeStr . ':00' : null;

            $normalized[] = [
                'productsID' => (int) $w->products_id,
                'coursesHeadingsID' => (int) $w->courses_headings_id,
                'courseHeadingName' => $w->title,
                'styleName' => $w->style_name,
                'price' => $priceVal,
                'fullPrice' => $fullPriceVal,
                'priceListType' => $priceListType,
                'priceListPositionName' => $priceListPositionName,
                'pricelistPositionsTypesDVID' => $pricelistPositionsTypesDVID,
                'dateFrom' => $dateFromStr,
                'dateTo' => $dateToStr,
                'durationMinutes' => $durationInMinutes,
                'available' => (bool) (!$w->is_closed && $w->available_places > 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $normalized,
        ]);
    }

    /**
     * POST /api/orders/workshops/checkout
     */
    public function checkout(Request $request, CourseHeadingPricingService $pricingService, CrmOrderClient $crmClient)
    {
        $request->validate([
            'type'                => ['required', 'string', 'in:ygm,euro'],
            'participantID'       => ['required', 'integer'],
            'categoryID'          => ['required', 'integer'],
            'selectedProductsIDs' => ['required', 'array'],
            'selectedProductsIDs.*' => ['integer'],
        ]);

        $type = $request->input('type');
        $participantID = (int) $request->input('participantID');
        $categoryID = (int) $request->input('categoryID');
        $selectedIDs = $request->input('selectedProductsIDs');

        if ($type === 'ygm') {
            $workshops = WorkshopYgm::whereIn('products_id', $selectedIDs);
        } else {
            $workshops = WorkshopEuropean::whereIn('products_id', $selectedIDs);
        }
        $this->withProductsLevel3Category($workshops);
        $this->whereProductsLevel3Category($workshops, $categoryID);
        $workshops = $workshops->get();

        if ($workshops->count() !== count($selectedIDs)) {
            return response()->json(['success' => false, 'message' => 'Wybrane warsztaty są niepoprawne lub wygasły.'], 400);
        }

        // 1. Validate Availability (Zero-Trust)
        foreach ($workshops as $w) {
            if ($w->is_closed || $w->available_places <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Brak wolnych miejsc na warsztat: {$w->title}."
                ], 400);
            }
        }

        // 2. Resolve pricing & Build selected hours
        $selectedHours = [];
        $rawTotal = 0.0;
        $seenPasses = [];

        foreach ($workshops as $w) {
            $prices = $pricingService->getPriceByCourseHeadingsID(
                (int) $w->courses_headings_id,
                $w->starts_at ? $w->starts_at->toDateString() : now()->toDateString(),
                (int) $w->products_id
            );

            if (count($prices) === 0) {
                return response()->json(['success' => false, 'message' => "Brak cennika dla warsztatu: {$w->title}"], 400);
            }

            $priceInfo = $prices[0];
            $priceVal = (float) ($priceInfo['amount'] ?? 0);
            $pricelistPositionsTypesDVID = (int) ($priceInfo['pricelistPositionsTypesDVID'] ?? 6);
            $priceListPositionName = $priceInfo['priceListPositionName'] ?? '';
            $priceListTemplateName = $priceInfo['priceListTemplateName'] ?? '';

            $value = strtoupper($priceListPositionName . ' ' . $priceListTemplateName);
            $priceListType = 'PL';
            if (str_contains($value, 'EU') || str_contains($value, 'INT')) {
                $priceListType = 'EU';
            }

            // Duration
            $durationInMinutes = (int) ($priceInfo['durationInMinutes'] ?? 0);

            // Date parsing
            $timeRange = $w->start_time;
            $timeParts = explode(' - ', $timeRange);
            $startTimeStr = count($timeParts) > 0 ? $timeParts[0] : '00:00';
            $endTimeStr = count($timeParts) > 1 ? $timeParts[1] : '23:59';

            $dateFromStr = $w->starts_at ? $w->starts_at->toDateString() . ' ' . $startTimeStr . ':00' : null;
            $dateToStr = $w->ends_at ? $w->ends_at->toDateString() . ' ' . $endTimeStr . ':00' : null;

            $hourItem = [
                'productsID' => (int) $w->products_id,
                'coursesHeadingsID' => (int) $w->courses_headings_id,
                'price' => $priceVal,
                'fullPrice' => $priceVal,
                'priceListType' => $priceListType,
                'priceListPositionName' => $priceListPositionName,
                'pricelistPositionsTypesDVID' => $pricelistPositionsTypesDVID,
                'dateFrom' => $dateFromStr,
                'dateTo' => $dateToStr,
                'durationMinutes' => $durationInMinutes,
                'styleName' => $w->style_name,
                'courseHeadingName' => $w->title,
                'instructorId' => (int) $w->products_id,
                'name' => $w->title,
                'instructorName' => $w->instructors,
                'time' => $w->start_time,
                'styleID' => $w->products_level3_category_id ?? $w->category_id,
            ];

            $selectedHours[] = $hourItem;
        }

        // 3. Resolve Full Pass details on backend (Zero-Trust)
        if ($type === 'euro') {
            // Find all workshops covered by selected Full Passes
            // EDM Full Pass is identified by pricelistPositionsTypesDVID == 2
            $fullPassNames = [];
            foreach ($selectedHours as $item) {
                if ($item['pricelistPositionsTypesDVID'] === 2) {
                    $fullPassNames[] = $item['priceListPositionName'];
                }
            }

            if (!empty($fullPassNames)) {
                $allEuroWorkshops = WorkshopEuropean::where('website_status_id', '!=', 0)
                    ->whereDate('ends_at', '>=', now()->toDateString());
                $this->withProductsLevel3Category($allEuroWorkshops);
                $allEuroWorkshops = $allEuroWorkshops->get();

                foreach ($allEuroWorkshops as $ew) {
                    $ewPrices = $pricingService->getPriceByCourseHeadingsID(
                        (int) $ew->courses_headings_id,
                        $ew->starts_at ? $ew->starts_at->toDateString() : now()->toDateString(),
                        (int) $ew->products_id
                    );

                    if (count($ewPrices) > 0) {
                        $ewPriceInfo = $ewPrices[0];
                        $ewPassName = $ewPriceInfo['priceListPositionName'] ?? '';
                        
                        if (in_array($ewPassName, $fullPassNames) && (int)($ewPriceInfo['pricelistPositionsTypesDVID'] ?? 6) !== 2) {
                            $timeRange = $ew->start_time;
                            $timeParts = explode(' - ', $timeRange);
                            $startTimeStr = count($timeParts) > 0 ? $timeParts[0] : '00:00';
                            $endTimeStr = count($timeParts) > 1 ? $timeParts[1] : '23:59';
                            $dateFromStr = $ew->starts_at ? $ew->starts_at->toDateString() . ' ' . $startTimeStr . ':00' : null;
                            $dateToStr = $ew->ends_at ? $ew->ends_at->toDateString() . ' ' . $endTimeStr . ':00' : null;

                            // Check if already in selectedHours
                            $alreadySelected = false;
                            foreach ($selectedHours as $sh) {
                                if ($sh['productsID'] === (int)$ew->products_id) {
                                    $alreadySelected = true;
                                    break;
                                }
                            }

                            if (!$alreadySelected) {
                                $selectedHours[] = [
                                    'productsID' => (int) $ew->products_id,
                                    'coursesHeadingsID' => (int) $ew->courses_headings_id,
                                    'price' => 0.0,
                                    'fullPrice' => 0.0,
                                    'priceListType' => 'EU',
                                    'priceListPositionName' => $ewPassName,
                                    'pricelistPositionsTypesDVID' => (int)($ewPriceInfo['pricelistPositionsTypesDVID'] ?? 6),
                                    'dateFrom' => $dateFromStr,
                                    'dateTo' => $dateToStr,
                                    'durationMinutes' => (int) ($ewPriceInfo['durationInMinutes'] ?? 0),
                                    'styleName' => $ew->style_name,
                                    'courseHeadingName' => $ew->title,
                                    'instructorId' => (int) $ew->products_id,
                                    'name' => $ew->title,
                                    'instructorName' => $ew->instructors,
                                    'time' => $ew->start_time,
                                    'styleID' => $ew->products_level3_category_id ?? $ew->category_id,
                                    'isPassIncluded' => true,
                                ];
                            }
                        }
                    }
                }
            }
        }

        // 4. Overlap Conflict Check
        for ($i = 0; $i < count($selectedHours); $i++) {
            for ($j = $i + 1; $j < count($selectedHours); $j++) {
                $item1 = $selectedHours[$i];
                $item2 = $selectedHours[$j];

                if ($item1['pricelistPositionsTypesDVID'] === 2 || $item2['pricelistPositionsTypesDVID'] === 2) {
                    continue;
                }

                if ($item1['dateFrom'] && $item1['dateTo'] && $item2['dateFrom'] && $item2['dateTo']) {
                    $start1 = Carbon::parse($item1['dateFrom']);
                    $end1 = Carbon::parse($item1['dateTo']);
                    $start2 = Carbon::parse($item2['dateFrom']);
                    $end2 = Carbon::parse($item2['dateTo']);

                    if ($start1->lt($end2) && $start2->lt($end1)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Konflikt terminów pomiędzy warsztatami: {$item1['courseHeadingName']} a {$item2['courseHeadingName']}."
                        ], 400);
                    }
                }
            }
        }

        // 5. Calculate raw totals (Full Passes counted only once by priceListPositionName)
        foreach ($selectedHours as $curr) {
            if ($curr['pricelistPositionsTypesDVID'] === 2) {
                if (!in_array($curr['priceListPositionName'], $seenPasses)) {
                    $seenPasses[] = $curr['priceListPositionName'];
                    $rawTotal += (float) $curr['fullPrice'];
                }
            } else {
                $rawTotal += (float) $curr['price'];
            }
        }

        // 6. Calculate Discounts (Zero-Trust YGM)
        $discountPercentage = 0.0;
        if ($type === 'ygm') {
            $plCount = 0;
            $euCount = 0;
            foreach ($selectedHours as $item) {
                if ($item['priceListType'] === 'PL') $plCount++;
                if ($item['priceListType'] === 'EU') $euCount++;
            }

            if ($plCount >= 6 && $euCount >= 2) {
                $discountPercentage = 20.0;
            } elseif ($plCount >= 3 && $euCount >= 1) {
                $discountPercentage = 10.0;
            }
        }

        $discountAmount = round($rawTotal * ($discountPercentage / 100.0), 2);
        $finalTotal = round($rawTotal - $discountAmount, 2);

        // 7. Save selection via CRM getEuWorkshopGroupPrices
        $trackingId = (string) Str::uuid();
        $productsLevel2DVID = $type === 'ygm' ? 55 : 56;

        $savePayload = [
            'keyController' => 'setEuWorkshopGroupPrices',
            'guid' => $trackingId,
            'selectedHours' => $selectedHours,
            'productsTypeDVID' => $categoryID,
            'productsLevel2DVID' => $productsLevel2DVID,
            'participantID' => $participantID,
            'amountBeforeDiscount' => $rawTotal,
            'discountPercentage' => $discountPercentage,
            'discountAmount' => $discountAmount,
            'amountAfterDiscount' => $finalTotal,
            'current_LocalizationsID' => '0',
        ];

        try {
            $crmClient->calculateWorkshopPricing($savePayload, $trackingId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd zapisu koszyka w CRM: ' . $e->getMessage()
            ], 500);
        }

        // 8. Submit OrderPayment
        $orderPaymentData = [
            'type' => 'OrderPayment',
            'body' => [
                'guid' => $trackingId,
                'paymentMethodsP24' => '5',
                'returnUrl' => 'https://panelklienta.egurrola.com/Pulpit',
                'current_LocalizationsID' => -1,
            ],
        ];

        try {
            $response = $crmClient->createOrder($orderPaymentData, $trackingId);
            
            $rawPayload = $response->raw;
            $paymentUrl = $rawPayload['payment_url'] ?? $rawPayload['payment_token'] ?? $rawPayload['html'] ?? $rawPayload['token'] ?? null;

            if ($paymentUrl) {
                return response()->json([
                    'success' => true,
                    'paymentUrl' => $paymentUrl,
                ]);
            }

            $requestId = $rawPayload['requestId'] ?? null;
            $recheck = $rawPayload['recheck'] ?? false;

            if ($recheck && $requestId) {
                return response()->json([
                    'success' => true,
                    'pending' => true,
                    'requestId' => $requestId,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Nie udało się wygenerować linku płatności.',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Błąd procesowania płatności: ' . $e->getMessage()
            ], 500);
        }
    }
}
