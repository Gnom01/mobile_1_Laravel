<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CourseHeadingPricingService
 *
 * 1:1 port of CRM getPriceByCourseHeadingsID logic.
 * All method names, field names and business rules mirror the CRM implementation.
 * "paymentShedule" spelling is intentional (matches CRM).
 */
class CourseHeadingPricingService
{
    // periodsOfValidityDVID constants
    private const PERIOD_SCHOOL_YEAR      = 1;
    private const PERIOD_SEMESTER         = 2;
    private const PERIOD_CALENDAR_MONTH   = 3;
    private const PERIOD_MONTH_FROM_DATE  = 4;
    private const PERIOD_DAY              = 5;
    private const PERIOD_EVENT            = 7;
    private const PERIOD_UNLIMITED        = 8;

    // paymentTypesDVID constants
    private const PAYMENT_UPFRONT         = 1;
    private const PAYMENT_INSTALLMENT     = 2;
    private const PAYMENT_FREE            = 3;
    private const PAYMENT_CAMP            = 9;

    // pricelistPositionsTypesDVID
    private const POSITION_TYPE_CONTRACT  = 1;
    private const POSITION_TYPE_SINGLE    = 6;

    // Discount type ID for pro-rata discount
    private const PRORATA_DISCOUNT_ID     = 7;

    // ──────────────────────────────────────────────────────────────────────────
    // PUBLIC ENTRY POINT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Main method — mirror of CRM getPriceByCourseHeadingsID.
     *
     * @param  int         $coursesHeadingsID
     * @param  string      $startDate   Y-m-d
     * @param  int|null    $productsID  optional filter
     * @return array
     */
    public function getPriceByCourseHeadingsID(
        int    $coursesHeadingsID,
        string $startDate,
        ?int   $productsID = null
    ): array {
        $forDate = Carbon::parse($startDate);

        // 1. Fetch course
        $course = $this->getCourse($coursesHeadingsID);
        if (!$course) {
            return [];
        }

        // 2. Fetch product list
        $products = $this->getProductList($coursesHeadingsID);

        // 3. Optional single-product filter
        if ($productsID !== null) {
            $products = $products->filter(
                fn($p) => (int)($p['productsID'] ?? 0) === $productsID
            )->values();
        }

        // 4. Enrich each product with price + schedule
        $result = [];
        foreach ($products as $product) {
            $result[] = $this->enrichProduct($product, $course, $forDate);
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 1 — FETCH COURSE (CoursesHeadings)
    // ──────────────────────────────────────────────────────────────────────────

    private function getCourse(int $coursesHeadingsID): ?array
    {
        $row = DB::table('courses')
            ->where('coursesHeadingsID', $coursesHeadingsID)
            ->first();

        if (!$row) {
            return null;
        }

        return (array) $row;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 2 — FETCH PRODUCT LIST
    // ──────────────────────────────────────────────────────────────────────────

    private function getProductList(int $coursesHeadingsID): Collection
    {
        $templateProducts    = $this->getTemplateProducts($coursesHeadingsID);
        $nonTemplateProducts = $this->getNonTemplateProducts($coursesHeadingsID);

        return $templateProducts->merge($nonTemplateProducts);
    }

    /**
     * A. Products based on price-list template (priceListsTemplatesPositionsID > 0).
     */
    private function getTemplateProducts(int $coursesHeadingsID): Collection
    {
        $rows = DB::table('products as p')
            ->join('priceliststemplatespositions as pltp',
                'p.priceListsTemplatesPositionsID', '=', 'pltp.priceliststemplatespositionsid')
            ->join('priceliststemplates as plt',
                'pltp.priceliststemplatesid', '=', 'plt.priceliststemplatesid')
            ->leftJoin('localizations as loc',
                'pltp.localizationsid', '=', 'loc.LocalizationsID')
            ->where('p.CoursesHeadingsID', $coursesHeadingsID)
            ->where('p.Cancelled', 0)
            ->where('p.PriceListsTemplatesPositionsID', '>', 0)
            ->select([
                'p.ProductsID as productsID',
                'p.PriceListsTemplatesID as priceListsTemplatesID',
                'p.ProductsLevel1DVID as productsLevel1DVID',
                'p.ProductsLevel2DVID as productsLevel2DVID',
                'p.ProductsLevel3DVID as productsLevel3DVID',
                'p.PriceListsTemplatesPositionsID as priceListsTemplatesPositionsID',
                'p.CoursesHeadingsID as coursesHeadingsID',
                'p.NumberOfLessons as numberOfLessons',
                'p.DurationInMinutes as durationInMinutes',
                'plt.PriceListTemplateName as priceListTemplateName',
                'plt.ExpirationDateFrom as expirationDateFrom',
                'plt.ExpirationDateTo as expirationDateTo',
                'pltp.pricelistpositionname as priceListPositionName',
                'pltp.pricelistpositionstypesdvid as pricelistPositionsTypesDVID',
                'pltp.periodsofvaliditydvid as periodsOfValidityDVID',
                'pltp.numberofperiods as numberOfPeriods',
                'pltp.unitsofaccountdvid as unitsOfAccountDVID',
                'pltp.numberofunitsaccount as numberOfUnitsAccount',
                'pltp.expirationdate as expirationDate',
                'pltp.unitdvid as unitDVID',
                'pltp.numberofunits as numberOfUnits',
                'pltp.unitamount as unitAmount',
                'pltp.amount as amount',
                'pltp.vatratesik as vatRatesIK',
                'pltp.localizationsid as localizationsID',
                'loc.LocalizationName as localizationName',
                'pltp.datefrom as dateFrom',
                'pltp.dateto as dateTo',
                'pltp.paymenttypesdvid as paymentTypesDVID',
                'pltp.numberoflessons as templateNumberOfLessons',
                'p.PaymentMethodsDVID as paymentMethodsRaw',
            ])
            ->get();

        return $rows->map(function ($row) use ($coursesHeadingsID) {
            $product = (array) $row;

            // Resolve dictionary names
            $product['pricelistPositionsTypesName'] = $this->getDictionaryName(
                'PricelistPositionsTypes', (int)($product['pricelistPositionsTypesDVID'] ?? 0)
            );
            $product['periodsOfValidityName'] = $this->getDictionaryName(
                'PeriodsOfValidity', (int)($product['periodsOfValidityDVID'] ?? 0)
            );
            $product['unitsOfAccountName'] = $this->getDictionaryName(
                'UnitsOfAccount', (int)($product['unitsOfAccountDVID'] ?? 0)
            );
            $product['unitsName'] = $this->getDictionaryName(
                'Units', (int)($product['unitDVID'] ?? 0)
            );
            $product['paymentDVIDName'] = $this->getDictionaryName(
                'PaymentTypes', (int)($product['paymentTypesDVID'] ?? 0)
            );

            // PaymentMethods from priceliststemplatespositionsdimensions
            $pmDims = DB::table('priceliststemplatespositionsdimensions')
                ->where('priceliststemplatespositionsid', $product['priceListsTemplatesPositionsID'])
                ->where('dictionaryname', 'PaymentMethods')
                ->where('cancelled', 0)
                ->get();

            $product['paymentMethodsDIDArray']  = $pmDims->pluck('dictionariesid')->toArray();
            $product['paymentMethodsDVIDArray'] = $pmDims->pluck('positiondvid')->toArray();

            // DurationInMinutes from coursesheadingsdimensions
            $durDim = DB::table('coursesheadingsdimensions')
                ->where('coursesheadingsid', $coursesHeadingsID)
                ->where('dictionaryname', 'DurationInMinutes')
                ->where('cancelled', 0)
                ->first();

            $product['durationInMinutesDVID']     = $durDim ? (int)$durDim->positiondvid : 0;
            $product['durationInMinutesDVIDName'] = $durDim
                ? $this->getDictionaryValueText('DurationInMinutes', (int)$durDim->positiondvid)
                : '';

            // CourseFrequency
            $freqDim = DB::table('coursesheadingsdimensions')
                ->where('coursesheadingsid', $coursesHeadingsID)
                ->where('dictionaryname', 'CourseFrequency')
                ->where('cancelled', 0)
                ->first();
            $product['courseFrequencyName'] = $freqDim
                ? $this->getDictionaryValueText('CourseFrequency', (int)$freqDim->positiondvid)
                : '';

            // Course name
            $course = DB::table('courses')
                ->where('coursesHeadingsID', $coursesHeadingsID)
                ->select('courseHeadingName')
                ->first();
            $product['courseHeadingName'] = $course ? $course->courseHeadingName : '';

            $product['_isTemplate'] = true;

            return $product;
        });
    }

    /**
     * B. Non-template products (priceListsTemplatesID = 0, priceListsTemplatesPositionsID = 0).
     */
    private function getNonTemplateProducts(int $coursesHeadingsID): Collection
    {
        $rows = DB::table('products as p')
            ->leftJoin('localizations as loc',
                'p.LocalizationsID', '=', 'loc.LocalizationsID')
            ->where('p.CoursesHeadingsID', $coursesHeadingsID)
            ->where('p.Cancelled', 0)
            ->where('p.PriceListsTemplatesID', 0)
            ->where('p.PriceListsTemplatesPositionsID', 0)
            ->select([
                'p.ProductsID as productsID',
                DB::raw('0 as priceListsTemplatesID'),
                'p.ProductsLevel1DVID as productsLevel1DVID',
                'p.ProductsLevel2DVID as productsLevel2DVID',
                'p.ProductsLevel3DVID as productsLevel3DVID',
                DB::raw('0 as priceListsTemplatesPositionsID'),
                'p.CoursesHeadingsID as coursesHeadingsID',
                'p.NumberOfLessons as numberOfLessons',
                'p.DurationInMinutes as durationInMinutes',
                DB::raw("'Spoza wzorca' as priceListTemplateName"),
                'p.StartingDate as expirationDateFrom',
                'p.ClosingDate as expirationDateTo',
                'p.ProductName as priceListPositionName',
                DB::raw('0 as pricelistPositionsTypesDVID'),
                'p.PeriodsOfValidityDVID as periodsOfValidityDVID',
                'p.NumberOfPeriods as numberOfPeriods',
                'p.UnitsOfAccountDVID as unitsOfAccountDVID',
                'p.NumberOfUnitsAccount as numberOfUnitsAccount',
                'p.ExpirationDate as expirationDate',
                DB::raw('0 as unitDVID'),
                DB::raw('0 as numberOfUnits'),
                'p.UnitPrice as unitAmount',
                'p.Price as amount',
                'p.VatRatesIK as vatRatesIK',
                'p.LocalizationsID as localizationsID',
                'loc.LocalizationName as localizationName',
                'p.StartingDate as dateFrom',
                'p.ClosingDate as dateTo',
                'p.PaymentTypesDVID as paymentTypesDVID',
                'p.NumberOfLessons as templateNumberOfLessons',
                'p.PaymentMethodsDVID as paymentMethodsRaw',
            ])
            ->get();

        return $rows->map(function ($row) use ($coursesHeadingsID) {
            $product = (array) $row;

            $product['pricelistPositionsTypesName'] = '';
            $product['periodsOfValidityName']       = $this->getDictionaryName(
                'PeriodsOfValidity', (int)($product['periodsOfValidityDVID'] ?? 0)
            );
            $product['unitsOfAccountName'] = $this->getDictionaryName(
                'UnitsOfAccount', (int)($product['unitsOfAccountDVID'] ?? 0)
            );
            $product['unitsName']      = '';
            $product['paymentDVIDName'] = $this->getDictionaryName(
                'PaymentTypes', (int)($product['paymentTypesDVID'] ?? 0)
            );

            // PaymentMethods from productsdimensions
            $pmDims = DB::table('productsdimensions')
                ->where('productsid', $product['productsID'])
                ->where('dictionaryname', 'PaymentMethods')
                ->where('cancelled', 0)
                ->get();

            $product['paymentMethodsDIDArray']  = $pmDims->pluck('dictionariesid')->toArray();
            $product['paymentMethodsDVIDArray'] = $pmDims->pluck('positiondvid')->toArray();

            // DurationInMinutes dimension
            $durDim = DB::table('coursesheadingsdimensions')
                ->where('coursesheadingsid', $coursesHeadingsID)
                ->where('dictionaryname', 'DurationInMinutes')
                ->where('cancelled', 0)
                ->first();
            $product['durationInMinutesDVID']     = $durDim ? (int)$durDim->positiondvid : 0;
            $product['durationInMinutesDVIDName'] = $durDim
                ? $this->getDictionaryValueText('DurationInMinutes', (int)$durDim->positiondvid)
                : '';

            // CourseFrequency
            $freqDim = DB::table('coursesheadingsdimensions')
                ->where('coursesheadingsid', $coursesHeadingsID)
                ->where('dictionaryname', 'CourseFrequency')
                ->where('cancelled', 0)
                ->first();
            $product['courseFrequencyName'] = $freqDim
                ? $this->getDictionaryValueText('CourseFrequency', (int)$freqDim->positiondvid)
                : '';

            $course = DB::table('courses')
                ->where('coursesHeadingsID', $coursesHeadingsID)
                ->select('courseHeadingName')
                ->first();
            $product['courseHeadingName'] = $course ? $course->courseHeadingName : '';

            $product['_isTemplate'] = false;

            return $product;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEP 3 — ENRICH PRODUCT WITH PRICE AND SCHEDULE
    // ──────────────────────────────────────────────────────────────────────────

    private function enrichProduct(array $product, array $course, Carbon $forDate): array
    {
        $typesDVID = (int)($product['pricelistPositionsTypesDVID'] ?? 0);

        if ($typesDVID === self::POSITION_TYPE_CONTRACT) {
            return $this->processContractProduct($product, $course, $forDate);
        }

        return $this->processRegularProduct($product, $course, $forDate);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REGULAR PRODUCTS (non-contract)
    // ──────────────────────────────────────────────────────────────────────────

    private function processRegularProduct(array $product, array $course, Carbon $forDate): array
    {
        $periods = $this->getFinishDate(
            $forDate,
            (int)($product['periodsOfValidityDVID'] ?? 0),
            (int)($product['numberOfPeriods'] ?? 0),
            $course
        );

        if (!empty($periods['message'])) {
            $product['currentPrice']      = -1;
            $product['currentPriceError'] = $periods['message'];
            $product['paymentShedule']    = [];
            $product['numberOfLessons']   = 0;
            $product['durationInMinutes'] = 0;
            $product['expirationDateFrom'] = null;
            $product['expirationDateTo']   = null;
            return $product;
        }

        $paymentTypesDVID = (int)($product['paymentTypesDVID'] ?? 0);

        switch ($paymentTypesDVID) {
            case self::PAYMENT_UPFRONT:
                return $this->processUpfront($product, $periods, $forDate);
            case self::PAYMENT_INSTALLMENT:
                return $this->processInstallment($product, $periods, $forDate);
            case self::PAYMENT_FREE:
                return $this->processFree($product, $periods, $forDate);
            case self::PAYMENT_CAMP:
                return $this->processCamp($product, $periods);
            default:
                return $this->errorProduct($product, 'Brak przepisu rozliczeniowego');
        }
    }

    // ─── 5A. Upfront ─────────────────────────────────────────────────────────

    private function processUpfront(array $product, array $periods, Carbon $forDate): array
    {
        $amount = (float)($product['amount'] ?? 0);

        $numberOfLessons   = (int)($product['pricelistPositionsTypesDVID'] ?? 0) === self::POSITION_TYPE_SINGLE
            ? 1
            : (int)($product['numberOfLessons'] ?? 0);
        $durationInMinutes = $this->calcDurationInMinutes(
            $product['durationInMinutesDVIDName'] ?? '', $numberOfLessons
        );

        $product['numberOfLessons']    = $numberOfLessons;
        $product['durationInMinutes']  = $durationInMinutes;
        $product['currentPrice']       = $amount;
        $product['currentPriceError']  = '';
        $product['expirationDateFrom'] = $periods['paymentFromDate'];
        $product['expirationDateTo']   = $periods['paymentToDate'];
        $product['paymentShedule']     = [
            $this->makeScheduleItem(
                1,
                $forDate->toDateString(),
                $amount,
                1,
                0,
                $periods['paymentFromDate'],
                $periods['paymentToDate']
            ),
        ];

        return $product;
    }

    // ─── 5B. Camp installment ─────────────────────────────────────────────────

    private function processCamp(array $product, array $periods): array
    {
        $installments = DB::table('productspaymentinstallments')
            ->where('productsid', $product['productsID'])
            ->where('cancelled', 0)
            ->orderBy('installmentpaymnetdate')
            ->get();

        // Extra dimensions: departurePoints, dietCamp
        $departureDims = DB::table('productsdimensions')
            ->where('productsid', $product['productsID'])
            ->where('dictionaryname', 'departurePoints')
            ->where('cancelled', 0)
            ->pluck('positiondvid')
            ->toArray();

        $dietDims = DB::table('productsdimensions')
            ->where('productsid', $product['productsID'])
            ->where('dictionaryname', 'dietCamp')
            ->where('cancelled', 0)
            ->pluck('positiondvid')
            ->toArray();

        $schedule = [];
        foreach ($installments as $i => $inst) {
            $date = $inst->installmentpaymnetdate ?? null;
            $schedule[] = $this->makeScheduleItem(
                $i + 1,
                $date,
                (float)$inst->installmentamount,
                1,
                0,
                $date,
                $date
            );
        }

        $product['paymentShedule']        = $schedule;
        $product['currentPrice']          = (float)($product['amount'] ?? 0);
        $product['currentPriceError']     = '';
        $product['expirationDateFrom']    = $product['expirationDateFrom'] ?? null;
        $product['expirationDateTo']      = $product['expirationDateTo'] ?? null;
        $product['paymentTypesDVID']      = self::PAYMENT_CAMP;
        $product['numberOfLessons']       = 0;
        $product['durationInMinutes']     = 0;
        $product['localizationSelectDVID'] = $departureDims;
        $product['dietCampDVID']           = $dietDims;

        return $product;
    }

    // ─── 5C. Regular installment ──────────────────────────────────────────────

    private function processInstallment(array $product, array $periods, Carbon $forDate): array
    {
        $months    = (int)($product['numberOfUnitsAccount'] ?? 0);
        $unitAmount = (float)($product['unitAmount'] ?? 0);
        $total      = (float)($product['amount'] ?? 0);

        if ($months <= 0) {
            return $this->errorProduct($product, 'Brak liczby rat');
        }

        $priceFrom = Carbon::parse($periods['priceFromDate']);
        $schedule  = [];

        for ($i = 0; $i < $months; $i++) {
            $currStart  = (clone $priceFrom)->addMonths($i)->startOfMonth();
            $currFinish = (clone $currStart)->endOfMonth();

            $paymentDate = $i === 0 ? $forDate->toDateString() : $currStart->toDateString();

            $schedule[] = $this->makeScheduleItem(
                $i + 1,
                $paymentDate,
                $unitAmount,
                1,
                0,
                $currStart->toDateString(),
                $currFinish->toDateString()
            );
        }

        // Rounding difference → add to first installment
        $roundDiff = round($total - $months * $unitAmount, 2);
        if (!empty($schedule) && $roundDiff != 0.0) {
            $schedule[0]['paymentPositionPrice'] = round(
                $schedule[0]['paymentPositionPrice'] + $roundDiff, 2
            );
        }

        $numberOfLessons   = (int)($product['numberOfLessons'] ?? 0);
        $durationInMinutes = $this->calcDurationInMinutes(
            $product['durationInMinutesDVIDName'] ?? '', $numberOfLessons
        );

        $product['numberOfLessons']    = $numberOfLessons;
        $product['durationInMinutes']  = $durationInMinutes;
        $product['currentPrice']       = $total;
        $product['currentPriceError']  = '';
        $product['expirationDateFrom'] = $periods['paymentFromDate'];
        $product['expirationDateTo']   = $periods['paymentToDate'];
        $product['paymentShedule']     = $schedule;

        return $product;
    }

    // ─── 5D. Free ─────────────────────────────────────────────────────────────

    private function processFree(array $product, array $periods, Carbon $forDate): array
    {
        $product['currentPrice']       = 0.0;
        $product['currentPriceError']  = '';
        $product['expirationDateFrom'] = $periods['paymentFromDate'];
        $product['expirationDateTo']   = $periods['paymentToDate'];
        $product['numberOfLessons']    = (int)($product['numberOfLessons'] ?? 0);
        $product['durationInMinutes']  = 0;
        $product['paymentShedule']     = [
            $this->makeScheduleItem(
                1,
                $forDate->toDateString(),
                0.0,
                1,
                0,
                $periods['paymentFromDate'],
                $periods['paymentToDate']
            ),
        ];

        return $product;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CONTRACT PRODUCTS (pricelistPositionsTypesDVID = 1)
    // ──────────────────────────────────────────────────────────────────────────

    private function processContractProduct(array $product, array $course, Carbon $forDate): array
    {
        // CRM: $baseForDate = today (used as paymentDate on pro-rata installment)
        $today = Carbon::today()->toDateString();

        // If forDate < course.startingDate, start from course beginning
        $courseStart = isset($course['startingDate']) && $course['startingDate']
            ? Carbon::parse($course['startingDate'])
            : null;

        if ($courseStart && $forDate->lt($courseStart)) {
            $forDate = clone $courseStart;
        }

        $periods = $this->getContractPaymentPeriod(
            $forDate,
            (int)($product['periodsOfValidityDVID'] ?? 0),
            (int)($product['numberOfPeriods'] ?? 0),
            $course
        );

        if (!empty($periods['message'])) {
            return $this->errorProduct($product, $periods['message']);
        }

        $priceFrom  = $periods['priceFromDate'];
        $payFrom    = $periods['paymentFromDate'];
        $payTo      = $periods['paymentToDate'];
        $coursesHID = (int)($product['coursesHeadingsID'] ?? 0);

        // All lessons in the full price period (CRM: includeCancelled not used in lesson count here)
        $allLessonsData = $this->countLessonsAndMinutes($coursesHID, $priceFrom, $payTo);

        if ($allLessonsData['numberOfLessons'] <= 0 && $allLessonsData['durationInMinutes'] <= 0) {
            return $this->errorProduct($product, 'Błąd w wyznaczaniu ceny');
        }

        $allUnits = $allLessonsData['durationInMinutes'] > 0
            ? $allLessonsData['durationInMinutes']
            : $allLessonsData['numberOfLessons'];

        // Lessons in actually paid period
        $lessonsData = $this->countLessonsAndMinutes($coursesHID, $payFrom, $payTo);
        $paidUnits   = $lessonsData['durationInMinutes'] > 0
            ? $lessonsData['durationInMinutes']
            : $lessonsData['numberOfLessons'];

        $totalAmount = (float)($product['amount'] ?? 0);
        $price       = round(($totalAmount / $allUnits) * $paidUnits, 2);
        $unitAmount  = (float)($product['unitAmount'] ?? 0);

        $product['numberOfLessons']    = $lessonsData['numberOfLessons'];
        $product['durationInMinutes']  = $lessonsData['durationInMinutes'];
        $product['currentPrice']       = $price;
        $product['currentPriceError']  = '';
        $product['expirationDateFrom'] = $payFrom;
        $product['expirationDateTo']   = $payTo;

        $paymentTypesDVID = (int)($product['paymentTypesDVID'] ?? 0);
        $courseClose = isset($course['closingDate']) && $course['closingDate']
            ? $course['closingDate']
            : $payTo;

        $product['paymentShedule'] = $this->buildContractSchedule(
            $product,
            $price,
            $totalAmount,
            $unitAmount,
            $paymentTypesDVID,
            $priceFrom,
            $payFrom,
            $payTo,
            $courseClose,
            $today,
            $coursesHID
        );

        return $product;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CONTRACT SCHEDULE BUILDER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build the payment schedule for a contract product.
     * 1:1 port of CRM createContractsPaymentShedulePositions installment logic.
     */
    private function buildContractSchedule(
        array  $product,
        float  $price,
        float  $totalAmount,
        float  $unitAmount,
        int    $paymentTypesDVID,
        string $priceFrom,
        string $payFrom,
        string $payTo,
        string $courseClose,
        string $today,
        int    $coursesHID
    ): array {
        // ── Upfront ──────────────────────────────────────────────────────────
        if ($paymentTypesDVID === self::PAYMENT_UPFRONT) {
            $isFullUnit = ($totalAmount == $price) ? 1 : 0;
            return [
                $this->makeScheduleItem(1, $today, $price, $isFullUnit, 0, $payFrom, $payTo),
            ];
        }

        // ── Installment ───────────────────────────────────────────────────────
        if ($paymentTypesDVID === self::PAYMENT_INSTALLMENT) {
            $priceFromDate         = Carbon::parse($priceFrom);
            $payFromDate           = Carbon::parse($payFrom);
            $payToDate             = Carbon::parse($payTo);
            $periodsOfValidityDVID = (int)($product['periodsOfValidityDVID'] ?? 0);

            // CRM countMonths: inclusive calendar-month count
            $months     = $this->contractCountMonths($priceFromDate, $payToDate);
            $monthsLeft = $this->contractCountMonths($payFromDate, $payToDate);

            $paymentSchedule = [];
            $unitsAmountsSum = 0.0;
            $proRataIndex    = $months - $monthsLeft + 1;

            // Loop backwards: months → 1 (mirrors CRM's descending for-loop)
            for ($i = $months; $i >= 1; $i--) {

                if ($i === $proRataIndex) {
                    // Pro-rata installment: receives the remaining amount
                    $currentAmount = $price - $unitsAmountsSum;
                } elseif (($price - $unitsAmountsSum - $unitAmount) > 0) {
                    $currentAmount = $unitAmount;
                } elseif (($price - $unitsAmountsSum) > 0) {
                    $currentAmount = $price - $unitsAmountsSum;
                } else {
                    $currentAmount = 0.0;
                }

                // Calculate period dates
                if ($periodsOfValidityDVID === self::PERIOD_MONTH_FROM_DATE) {
                    $currStart  = (clone $priceFromDate)->addMonths($i - 1)->toDateString();
                    $currFinish = (clone $priceFromDate)->addMonths($i)->subDay()->toDateString();
                } else {
                    $base       = Carbon::create($priceFromDate->year, $priceFromDate->month, 1);
                    $currStart  = (clone $base)->addMonths($i - 1)->toDateString();
                    $currFinish = (clone $base)->addMonths($i)->subDay()->toDateString();
                }
                $paymentDate = $currStart;

                // CRM special overrides
                if ($i === $proRataIndex) {
                    $currStart   = $payFrom;      // partial-month starts on actual payFrom day
                    $paymentDate = $today;         // baseForDate
                } elseif ($i === $months) {
                    $currFinish  = $payTo;         // last month clipped to actual payTo
                } elseif ($i === 1) {
                    $currStart   = $priceFrom;     // first month starts on priceFrom
                }

                $currentAmount = round($currentAmount, 2);

                // Clamp end date to course closing date
                if ($currFinish > $courseClose) {
                    $currFinish = $courseClose;
                }

                if ($currentAmount == $unitAmount && $unitAmount > 0) {
                    $isFullUnit = 1;
                    $isVoid     = 0;
                } elseif ($currentAmount > 0) {
                    $isFullUnit = 0;
                    $isVoid     = 0;
                } else {
                    $isFullUnit    = 0;
                    $isVoid        = 1;
                    $currentAmount = 0.0;
                }

                $paymentSchedule[$i - 1] = $this->makeScheduleItem(
                    $i,
                    $paymentDate,
                    $currentAmount,
                    $isFullUnit,
                    $isVoid,
                    $currStart,
                    $currFinish
                );

                $unitsAmountsSum += $currentAmount;
            }

            // Rounding correction on first item (index 0 = earliest month)
            if (!empty($paymentSchedule[0]) && (float)$paymentSchedule[0]['paymentPositionPrice'] > 0) {
                $roundDiff = round($unitsAmountsSum - $price, 2);
                $paymentSchedule[0]['paymentPositionPrice'] = round(
                    (float)$paymentSchedule[0]['paymentPositionPrice'] + $roundDiff,
                    2
                );
            }

            // Reverse to chronological order (CRM: array_reverse)
            $schedule = array_reverse($paymentSchedule);

            // Apply pro-rata discount across all non-void installments
            $schedule = $this->applyProRataDiscount($schedule, $unitAmount, $today, $coursesHID);

            // Remove void installments (price=0, already past) — not relevant for mobile client
            $schedule = array_values(array_filter($schedule, function ($item) {
                return !$item['isVoid'] && (float)$item['paymentPositionPrice'] > 0;
            }));

            // Renumber sequentially after filtering
            foreach ($schedule as $i => $item) {
                $schedule[$i]['countNumber'] = $i + 1;
            }

            return $schedule;
        }

        // ── Other payment types — single item ─────────────────────────────────
        return [
            $this->makeScheduleItem(1, $today, $price, 0, 0, $payFrom, $payTo),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PRO-RATA DISCOUNT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * 1:1 port of CRM pro-rata discount logic.
     *
     * When the pro-rata installment price > unitAmount, the excess (needCap) is
     * distributed evenly across ALL non-void installments (including pro-rata).
     * Each installment's gross price is inflated by its share of needCap, and
     * discountCash is set to the same value so the net (paymentPositionPriceDiscount)
     * equals unitAmount for every installment.
     */
    private function applyProRataDiscount(
        array  $schedule,
        float  $unitAmount,
        string $today,
        int    $coursesHID
    ): array {
        // Collect all non-void, non-zero installments
        $nonVoidIndexes = [];
        $proRataIdx     = null;

        foreach ($schedule as $idx => $inst) {
            if (!$inst['isVoid'] && (float)$inst['paymentPositionPrice'] > 0) {
                $nonVoidIndexes[] = $idx;
                // Pro-rata installment: paymentDate === today (baseForDate)
                if ($proRataIdx === null && $inst['paymentDate'] === $today) {
                    $proRataIdx = $idx;
                }
            }
        }

        // Fallback: first non-void installment
        if ($proRataIdx === null && !empty($nonVoidIndexes)) {
            $proRataIdx = $nonVoidIndexes[0];
        }

        if ($proRataIdx === null) {
            return $schedule;
        }

        $proRataPrice = (float)$schedule[$proRataIdx]['paymentPositionPrice'];

        // Only apply discount if pro-rata > unitAmount and unitAmount > 0
        if ($unitAmount <= 0 || $proRataPrice <= $unitAmount) {
            return $schedule;
        }

        $needCap = round($proRataPrice - $unitAmount, 2);
        $nRat    = count($nonVoidIndexes);

        if ($nRat > 1) {
            $commonDiscount  = floor($needCap / $nRat * 100) / 100;
            $commonRemainder = round($needCap - ($commonDiscount * $nRat), 2);
        } else {
            $commonDiscount  = $needCap;
            $commonRemainder = 0.0;
        }

        $defId        = self::PRORATA_DISCOUNT_ID;
        $minutesCache = [];

        foreach ($nonVoidIndexes as $pos => $idx) {
            $inst = &$schedule[$idx];

            // Look up lesson minutes for this installment's period
            $periodKey = ($inst['periodFromDate'] ?? '') . '|' . ($inst['periodToDate'] ?? '');
            if (!isset($minutesCache[$periodKey])) {
                $minRes = $this->countLessonsAndMinutes(
                    $coursesHID,
                    $inst['periodFromDate'] ?? '',
                    $inst['periodToDate'] ?? ''
                );
                $minutesCache[$periodKey] = $minRes['durationInMinutes'];
            }
            $minutesThis = $minutesCache[$periodKey];

            // Discount share for this installment (first gets the rounding remainder)
            $thisDiscount = ($pos === 0)
                ? round($commonDiscount + $commonRemainder, 2)
                : $commonDiscount;

            // Cap pro-rata to unitAmount, then inflate by thisDiscount
            // Net (paymentPositionPriceDiscount) will equal unitAmount
            if ($idx === $proRataIdx) {
                $inst['paymentPositionPrice'] = $unitAmount;
            }
            $inst['paymentPositionPrice'] = round((float)$inst['paymentPositionPrice'] + $thisDiscount, 2);

            // Safety: discount cannot exceed gross price
            if ($thisDiscount > $inst['paymentPositionPrice']) {
                $thisDiscount = $inst['paymentPositionPrice'];
            }

            $perMinute = $minutesThis > 0 ? round($thisDiscount / $minutesThis, 8) : 0.0;

            $existingDiscount = isset($inst['discountCash']) ? (float)$inst['discountCash'] : 0.0;
            $inst['discountCash'] = round($existingDiscount + $thisDiscount, 2);
            $inst['paymentPositionPriceDiscount'] = round(
                (float)$inst['paymentPositionPrice'] - $inst['discountCash'],
                2
            );

            $basePrice   = (float)$inst['paymentPositionPriceDiscount'];
            $discountPct = $basePrice > 0
                ? round(($inst['discountCash'] / $basePrice) * 100, 2)
                : 0.0;

            $inst['discountProcent']          = $discountPct;
            $inst['discountValue']            = [$defId => $thisDiscount];
            $inst['discountFromDate']         = [$defId => $inst['periodFromDate'] ?? null];
            $inst['discountToDate']           = [$defId => $inst['periodToDate'] ?? null];
            $inst['numberOfDiscountMinutes']  = [$defId => $minutesThis];
            $inst['numberOfDiscountLessons']  = [$defId => 0];
            $inst['discountAmountPerMinute']  = [$defId => $perMinute];
            $inst['discountAmountPerLessons'] = [$defId => 0];
        }

        return $schedule;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getFinishDate — for regular (non-contract) products
    // ──────────────────────────────────────────────────────────────────────────

    private function getFinishDate(
        Carbon $forDate,
        int    $periodsOfValidityDVID,
        int    $numberOfPeriods,
        array  $course
    ): array {
        $courseClose = isset($course['closingDate'])
            ? Carbon::parse($course['closingDate'])
            : null;

        $courseStart = isset($course['startingDate'])
            ? Carbon::parse($course['startingDate'])
            : null;

        switch ($periodsOfValidityDVID) {

            case self::PERIOD_SCHOOL_YEAR:
                return $this->finishDateSchoolYear($forDate, $courseClose);

            case self::PERIOD_SEMESTER:
                return $this->finishDateSemester($forDate, $courseClose);

            case self::PERIOD_CALENDAR_MONTH: {
                $from = (clone $forDate)->startOfMonth();
                $to   = (clone $from)->addMonths($numberOfPeriods)->subDay();
                if ($courseClose && $to->gt($courseClose)) {
                    $to = clone $courseClose;
                }
                return [
                    'priceFromDate'   => $from->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            case self::PERIOD_MONTH_FROM_DATE: {
                $to = (clone $forDate)->addMonths($numberOfPeriods)->subDay();
                if ($courseClose && $to->gt($courseClose)) {
                    $to = clone $courseClose;
                }
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            case self::PERIOD_DAY: {
                $to = (clone $forDate)->addDays($numberOfPeriods);
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            case self::PERIOD_EVENT:
                return [
                    'priceFromDate'   => $courseStart ? $courseStart->toDateString() : $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $courseClose ? $courseClose->toDateString() : $forDate->toDateString(),
                    'message'         => '',
                ];

            case self::PERIOD_UNLIMITED:
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => '2099-01-01',
                    'message'         => '',
                ];

            default:
                return [
                    'priceFromDate'   => '',
                    'paymentFromDate' => '',
                    'paymentToDate'   => '',
                    'message'         => 'Brak przepisu rozliczeniowego',
                ];
        }
    }

    private function finishDateSchoolYear(Carbon $forDate, ?Carbon $courseClose): array
    {
        // Jan–Jun → move to next school year (starting Sep of previous year)
        $year = $forDate->month <= 6
            ? $forDate->year
            : $forDate->year + 1;

        $season = DB::table('seasons')
            ->where('toyear', $year)
            ->where('cancelled', 0)
            ->first();

        if (!$season) {
            return [
                'priceFromDate' => '', 'paymentFromDate' => '', 'paymentToDate' => '',
                'message' => 'Brak definicji sezonu',
            ];
        }

        $from = Carbon::parse($season->fromdate);
        $to   = Carbon::parse($season->todate);

        if ($courseClose && $to->gt($courseClose)) {
            $to = clone $courseClose;
        }

        return [
            'priceFromDate'   => $from->toDateString(),
            'paymentFromDate' => $forDate->toDateString(),
            'paymentToDate'   => $to->toDateString(),
            'message'         => '',
        ];
    }

    private function finishDateSemester(Carbon $forDate, ?Carbon $courseClose): array
    {
        $month = $forDate->month;

        if ($month >= 9 || $month <= 1) {
            // Winter semester ends Jan 31
            $to = Carbon::create($forDate->month <= 1 ? $forDate->year : $forDate->year + 1, 1, 31);
        } else {
            // Summer semester ends with season toDate
            $year   = $forDate->month <= 6 ? $forDate->year : $forDate->year + 1;
            $season = DB::table('seasons')->where('toyear', $year)->where('cancelled', 0)->first();
            $to     = $season ? Carbon::parse($season->todate) : Carbon::create($year, 6, 30);
        }

        if ($courseClose && $to->gt($courseClose)) {
            $to = clone $courseClose;
        }

        return [
            'priceFromDate'   => $forDate->toDateString(),
            'paymentFromDate' => $forDate->toDateString(),
            'paymentToDate'   => $to->toDateString(),
            'message'         => '',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getContractPaymentPeriod — for contract products
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * 1:1 port of CRM getContractPaymentPeriod.
     * Returns ['priceFromDate', 'paymentFromDate', 'paymentToDate', 'message'].
     * Non-empty 'message' means an error.
     */
    private function getContractPaymentPeriod(
        Carbon $forDate,
        int    $periodsOfValidityDVID,
        int    $numberOfPeriodsOfValidity,
        array  $course
    ): array {
        $pricingMonth = (int)$forDate->format('n');
        $courseClose  = isset($course['closingDate']) && $course['closingDate']
            ? Carbon::parse($course['closingDate'])
            : null;
        $courseStart  = isset($course['startingDate']) && $course['startingDate']
            ? Carbon::parse($course['startingDate'])
            : null;

        switch ($periodsOfValidityDVID) {

            // 1 – Rok szkolny (school year)
            case self::PERIOD_SCHOOL_YEAR: {
                if ($numberOfPeriodsOfValidity != 1) {
                    return $this->periodError('Brak przepisu rozliczeniowego');
                }
                $n = $numberOfPeriodsOfValidity;
                if ($pricingMonth <= 6) {
                    $n--;
                }
                $year   = (clone $forDate)->addYears($n)->year;
                $season = DB::table('seasons')
                    ->where('toyear', $year)
                    ->where('cancelled', 0)
                    ->first();
                if (!$season || empty($season->todate)) {
                    return $this->periodError('Brak definicji sezonu');
                }
                $to = Carbon::parse($season->todate);
                if ($courseClose && $to->gt($courseClose)) {
                    $to = clone $courseClose;
                }
                return [
                    'priceFromDate'   => $season->fromdate,
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            // 2 – Semestr (semester)
            case self::PERIOD_SEMESTER: {
                $result = $this->calculatePeriodBySemester($forDate, $numberOfPeriodsOfValidity);
                if ($result['success']) {
                    return [
                        'priceFromDate'   => $forDate->toDateString(),
                        'paymentFromDate' => $forDate->toDateString(),
                        'paymentToDate'   => $result['endDate'],
                        'message'         => '',
                    ];
                }
                return $this->periodError($result['message'] ?? 'Brak przepisu rozliczeniowego');
            }

            // 3 – Miesiąc kalendarzowy (calendar month)
            case self::PERIOD_CALENDAR_MONTH: {
                $from    = (clone $forDate)->startOfMonth();
                $to      = (clone $from)->addMonths($numberOfPeriodsOfValidity)->subDay();
                $toYM    = $to->format('Y-m');
                $closeYM = $courseClose ? $courseClose->format('Y-m') : null;
                if ($closeYM && $toYM > $closeYM) {
                    return $this->periodError('Produkt już niedostępny');
                }
                if ($courseClose && $to->gt($courseClose)) {
                    $to = clone $courseClose;
                }
                return [
                    'priceFromDate'   => $from->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            // 4 – Miesiąc od daty (month from date)
            case self::PERIOD_MONTH_FROM_DATE: {
                $to      = (clone $forDate)->addMonths($numberOfPeriodsOfValidity)->subDay();
                $toYM    = $to->format('Y-m');
                $closeYM = $courseClose ? $courseClose->format('Y-m') : null;
                if ($closeYM && $toYM > $closeYM) {
                    return $this->periodError('Produkt już niedostępny');
                }
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            // 5 – Dzień (day)
            case self::PERIOD_DAY: {
                $to = (clone $forDate)->addDays($numberOfPeriodsOfValidity);
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $to->toDateString(),
                    'message'         => '',
                ];
            }

            // 7 – Wydarzenie (event)
            case self::PERIOD_EVENT:
                return [
                    'priceFromDate'   => $courseStart ? $courseStart->toDateString() : $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => $courseClose ? $courseClose->toDateString() : $forDate->toDateString(),
                    'message'         => '',
                ];

            // 8 – Bezterminowo (unlimited)
            case self::PERIOD_UNLIMITED:
                return [
                    'priceFromDate'   => $forDate->toDateString(),
                    'paymentFromDate' => $forDate->toDateString(),
                    'paymentToDate'   => '2099-01-01',
                    'message'         => '',
                ];

            default:
                return $this->periodError('Brak przepisu rozliczeniowego');
        }
    }

    private function periodError(string $message): array
    {
        return [
            'priceFromDate'   => '',
            'paymentFromDate' => '',
            'paymentToDate'   => '',
            'message'         => $message,
        ];
    }

    /**
     * 1:1 port of CRM calculatePeriodBySemester.
     * Divides the calendar year into three zones:
     *   A – tail of winter semester (Jan 1 – Jan 31)
     *   B – summer semester (Feb 1 – season.toDate)
     *   C – start of new winter semester (after season.toDate – Dec 31)
     * Returns ['success', 'endDate' (Y-m-d string), 'message'].
     */
    private function calculatePeriodBySemester(Carbon $date, int $periods): array
    {
        if ($periods < 1) {
            return ['success' => false, 'message' => 'incorrect_period_count', 'endDate' => null];
        }

        $year   = (int)$date->format('Y');
        $season = DB::table('seasons')
            ->where('toyear', $year)
            ->where('cancelled', 0)
            ->first();

        if (!$season) {
            return ['success' => false, 'message' => 'start_season_error', 'endDate' => null];
        }
        if (empty($season->todate)) {
            return ['success' => false, 'message' => 'start_season_incorrect', 'endDate' => null];
        }

        // Winter semester always ends Jan 31
        $winterEndMonth = 1;
        $winterEndDay   = 31;

        $startSeasonEnd = Carbon::parse($season->todate);
        $summerEndMonth = (int)$startSeasonEnd->format('n');
        $summerEndDay   = (int)$startSeasonEnd->format('j');

        $winterSemEnd = Carbon::create($year, $winterEndMonth, $winterEndDay, 23, 59, 59);
        $summerSemEnd = Carbon::create($year, $summerEndMonth, $summerEndDay, 23, 59, 59);
        $yearEnd      = Carbon::create($year, 12, 31, 23, 59, 59);

        if ($date->lte($winterSemEnd)) {
            // Zone A: tail of previous winter semester
            $yearsToAdd      = (int)floor(($periods - 1) / 2);
            $endDateIsWinter = (($periods - 1) % 2) === 0;
        } elseif ($date->gt($summerSemEnd) && $date->lte($yearEnd)) {
            // Zone C: start of new winter semester
            $yearsToAdd      = (int)floor(($periods - 1) / 2) + 1;
            $endDateIsWinter = (($periods - 1) % 2) === 0;
        } else {
            // Zone B: summer semester
            $yearsToAdd      = (int)floor($periods / 2);
            $endDateIsWinter = ($periods % 2) === 0;
        }

        if ($endDateIsWinter) {
            $endDate = Carbon::create($year + $yearsToAdd, $winterEndMonth, $winterEndDay);
            return ['success' => true, 'endDate' => $endDate->toDateString(), 'message' => ''];
        }

        // Summer end: fetch the target season
        if ($yearsToAdd > 0) {
            $endSeason = DB::table('seasons')
                ->where('toyear', $year + $yearsToAdd)
                ->where('cancelled', 0)
                ->first();
            if (!$endSeason) {
                return ['success' => false, 'message' => 'end_season_error', 'endDate' => null];
            }
            if (empty($endSeason->todate)) {
                return ['success' => false, 'message' => 'end_season_incorrect', 'endDate' => null];
            }
            $endDate = Carbon::parse($endSeason->todate);
        } else {
            $endDate = Carbon::create($year, $summerEndMonth, $summerEndDay);
        }

        return ['success' => true, 'endDate' => $endDate->toDateString(), 'message' => ''];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // COUNT LESSONS AND MINUTES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Count lessons and total minutes for a course in the given date range.
     * Mirror of CRM schedule counting logic.
     */
    public function countLessonsAndMinutes(
        int    $coursesHeadingsID,
        string $fromDate,
        string $toDate
    ): array {
        if (empty($fromDate) || empty($toDate)) {
            return ['numberOfLessons' => 0, 'durationInMinutes' => 0];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->startOfDay();
        $testList = [];

        if ($from->gt($to)) {
            return ['numberOfLessons' => 0, 'durationInMinutes' => 0];
        }

        // Fetch active regular schedule entries for this course
        $schedules = DB::table('xschedules')
            ->where('coursesheadingsid', $coursesHeadingsID)
            ->where('cancelled', 0)
            ->where('sheduleitemtypedvid', 1)   // regular type only
            ->where('excludedfromweeklyschedule', 0)
            ->get();

        if ($schedules->isEmpty()) {
            return ['numberOfLessons' => 0, 'durationInMinutes' => 0];
        }

        // Index exception entries by parent schedule ID (exceptionintday = YYYYMMDD int)
        $exceptions = DB::table('xschedules')
            ->where('coursesheadingsid', $coursesHeadingsID)
            ->where('cancelled', 0)
            ->where('sheduleitemtypedvid', 9)
            ->whereNotNull('exceptionintday')
            ->get(['parent_id', 'exceptionintday'])
            ->groupBy('parent_id');

        // Fetch days off that overlap the date range
        $daysOff = DB::table('daysoff')
            ->where('cancelled', 0)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->whereNotNull('datato')
                         ->where('date', '<=', $to->toDateString())
                         ->where('datato', '>=', $from->toDateString());
                  });
            })
            ->get();

        $numberOfLessons = 0;
        $totalMinutes    = 0;
        $countedDates    = []; // deduplicate: one lesson per date regardless of how many schedules match

        // weekdaysdvid mapping: 1=Mon … 6=Sat, 7=Sun
        // Carbon::dayOfWeek: 0=Sun, 1=Mon … 6=Sat
        // Formula: carbonDow = (weekdaysdvid % 7)
        foreach ($schedules as $sched) {
            $schedWeekDayDVID = (int)$sched->weekdaysdvid;
            $carbonDow        = $schedWeekDayDVID % 7;   // 7→0 (Sun), 1→1 (Mon), …6→6 (Sat)

            $schedStart = $sched->timefrom ?? $sched->starttime ?? null;
            $schedEnd   = $sched->timeto   ?? $sched->endtime   ?? null;
            $schedLocID = (int)($sched->localizationsid ?? 0);

            // Duration per lesson in minutes
            $lessonMinutes = 0;
            if ($schedStart && $schedEnd) {
                try {
                    $startT        = Carbon::parse($schedStart);
                    $endT          = Carbon::parse($schedEnd);
                    $lessonMinutes = max(0, (int)$startT->diffInMinutes($endT, false));
                } catch (\Throwable $e) {
                    $lessonMinutes = 0;
                }
            }

            // Exception intdays (YYYYMMDD ints) for this schedule entry
            $exceptIntDays = [];
            if (isset($exceptions[$sched->id])) {
                $exceptIntDays = $exceptions[$sched->id]->pluck('exceptionintday')->map('intval')->all();
            }

            // Iterate every day in the range by Carbon (no dependency on `days` table)
            $cursor      = clone $from;
            $totalDays   = (int) $from->diffInDays($to) + 1;
            $iterCount   = 0;
            $schedLesson = 0;

            while ($cursor->lte($to)) {
                if ($cursor->dayOfWeek === $carbonDow) {
                    $intDate = (int)$cursor->format('Ymd');

                    // Skip recurrence exception days
                    if (in_array($intDate, $exceptIntDays, true)) {
                        $cursor->addDay();
                        $iterCount++;
                        continue;
                    }

                    // Check against days off
                    $isDayOff = false;
                    foreach ($daysOff as $off) {
                        $offType  = (int)($off->daysofftypesdvid ?? 0);
                        $offLocID = (int)($off->localizationsid ?? 0);

                        // Only apply global (type=1) or same-localization day off
                        if ($offType !== 1 && $offLocID !== $schedLocID) {
                            continue;
                        }

                        $offDate   = $off->date   ? Carbon::parse($off->date)->startOfDay()   : null;
                        $offDateTo = $off->datato ? Carbon::parse($off->datato)->startOfDay() : null;

                        if ($offDate && !$offDateTo) {
                            if ($cursor->isSameDay($offDate)) {
                                $isDayOff = true;
                                break;
                            }
                        } elseif ($offDate && $offDateTo) {
                            if ($cursor->between($offDate, $offDateTo)) {
                                $isDayOff = true;
                                break;
                            }
                        }
                    }

                    if (!$isDayOff) {
                        $dateStr = $cursor->toDateString();
                        if (!isset($countedDates[$dateStr])) {
                            $countedDates[$dateStr] = true;
                            $numberOfLessons++;
                            $schedLesson++;
                            $totalMinutes += $lessonMinutes;
                          
                            $testList[] = [
                                'coursesHeadingsID' => $coursesHeadingsID,
                                'scheduleID'       => $sched->id,
                                'date'             => $dateStr,
                                'lessonMinutes'    => $lessonMinutes,
                            ];
                        } 
                    } else {
                       
                    }
                }

                $cursor->addDay();
                $iterCount++;
            }
          
        }
        return [
            'numberOfLessons'   => $numberOfLessons,
            'durationInMinutes' => $totalMinutes,
            'lessons'           => $testList,
        ];
    }
 
    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function makeScheduleItem(
        int    $countNumber,
        ?string $paymentDate,
        float  $price,
        int    $isFullUnit,
        int    $isVoid,
        ?string $periodFrom,
        ?string $periodTo
    ): array {
        return [
            'countNumber'          => $countNumber,
            'paymentDate'          => $paymentDate,
            'paymentPositionPrice' => $price,
            'isFullUnitOfAccount'  => $isFullUnit,
            'isVoid'               => $isVoid,
            'periodFromDate'       => $periodFrom,
            'periodToDate'         => $periodTo,
            // Discount fields (populated only for pro-rata items)
            'discountCash'               => 0,
            'paymentPositionPriceDiscount' => $price,
            'discountProcent'            => 0,
            'discountValue'              => [],
            'discountFromDate'           => [],
            'discountToDate'             => [],
            'numberOfDiscountMinutes'    => [],
            'numberOfDiscountLessons'    => [],
            'discountAmountPerMinute'    => [],
            'discountAmountPerLessons'   => [],
        ];
    }

    private function errorProduct(array $product, string $message): array
    {
        $product['currentPrice']       = -1;
        $product['currentPriceError']  = $message;
        $product['paymentShedule']     = [];
        $product['numberOfLessons']    = 0;
        $product['durationInMinutes']  = 0;
        $product['expirationDateFrom'] = null;
        $product['expirationDateTo']   = null;
        return $product;
    }

    private function calcDurationInMinutes(string $dvName, int $lessons): int
    {
        if (is_numeric($dvName) && (float)$dvName > 0) {
            return (int)((float)$dvName * $lessons);
        }
        return 0;
    }

    private function monthsBetween(Carbon $from, Carbon $to): int
    {
        return (int)$from->diffInMonths($to);
    }

    /**
     * CRM countMonths: count calendar months inclusively by iterating 1st-of-month dates.
     * E.g. Sep→Jun (next year) = 10. Same as CRM's custom countMonths().
     */
    private function contractCountMonths(Carbon $start, Carbon $end): int
    {
        $counted = 0;
        $d  = Carbon::create($start->year, $start->month, 1);
        $df = Carbon::create($end->year,   $end->month,   1);
        while ($d->lte($df)) {
            $counted++;
            $d->addMonth();
        }
        return $counted;
    }

    private function getDictionaryName(string $dictionaryName, int $valueId): string
    {
        if ($valueId <= 0) {
            return '';
        }
        $row = DB::table('dictionaries')
            ->where('DictionaryName', $dictionaryName)
            ->where('ValueID', $valueId)
            ->where('Cancelled', 0)
            ->select('Name')
            ->first();
        return $row ? ($row->Name ?? '') : '';
    }

    private function getDictionaryValueText(string $dictionaryName, int $valueId): string
    {
        if ($valueId <= 0) {
            return '';
        }
        $row = DB::table('dictionaries')
            ->where('DictionaryName', $dictionaryName)
            ->where('ValueID', $valueId)
            ->where('Cancelled', 0)
            ->select('ValueText', 'Name')
            ->first();

        if (!$row) {
            return '';
        }
        return !empty($row->ValueText) ? $row->ValueText : ($row->Name ?? '');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ENTRY FEE CHECK
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * 1:1 port of CRM checkForEntryFee.
     *
     * Returns false   → user already has entryFee=1 (paid/exempt), no entry fee needed.
     * Returns array   → entry fee product data that should be offered to the user.
     * Returns null    → no entry fee product configured in the system.
     *
     * @param int $usersID           CRM usersID of the student/buyer
     * @param int $localizationsID   Localization to look up the product for
     * @return array|false|null
     */
    public function checkForEntryFee(int $usersID, int $localizationsID): array|false|null
    {
        // Step 1: check if the user already has entryFee=1 (already paid or exempt).
        // Mirror of CRM checkEntryFeeForUsersIDData – Products (Level1=4, Level2=11)
        // joined with users where entryFee=1.
        $alreadyPaid = DB::table('products as p')
            ->join('users as u', function ($join) use ($usersID) {
                $join->where('u.UsersID', '=', $usersID)
                     ->where('u.Cancelled', '=', 0)
                     ->where('u.entryFee', '=', 1);
            })
            ->leftJoin('localizations as l', 'p.localizationsID', '=', DB::raw('0'))
            ->where('p.cancelled', 0)
            ->where('p.ProductsLevel1DVID', 4)
            ->where('p.ProductsLevel2DVID', 11)
            ->orderByDesc('p.ProductsID')
            ->select([
                'p.productsID',
                'p.PaymentTypesDVID',
                'p.periodsOfValidityDVID',
                'p.numberOfPeriods',
                'p.unitsOfAccountDVID',
                'p.numberOfUnitsAccount',
                'p.unitPrice',
                'p.price',
                'p.vatRatesIK',
                'p.productName',
                'l.Default_VatRatesIK',
            ])
            ->first();

        if ($alreadyPaid && (int)$alreadyPaid->productsID > 0) {
            // User already has entry fee paid/exempt → no entry fee to charge.
            return false;
        }

        // Step 2: user has entryFee=0 → get the entry fee product for this localization.
        // Mirror of CRM getEntyFeeForLocalizationsIDData.
        $product = DB::table('products as p')
            ->join('users as u', function ($join) use ($usersID) {
                $join->where('u.UsersID', '=', $usersID)
                     ->where('u.Cancelled', '=', 0)
                     ->where('u.entryFee', '=', 0);
            })
            ->leftJoin('localizations as l', function ($join) use ($localizationsID) {
                $join->on('p.localizationsID', '=', DB::raw('0'))
                     ->where('l.localizationsID', '=', $localizationsID);
            })
            ->where('p.cancelled', 0)
            ->where('p.ProductsLevel1DVID', 4)
            ->where('p.ProductsLevel2DVID', 11)
            ->where(function ($q) use ($localizationsID) {
                $q->where('p.localizationsID', $localizationsID)
                  ->orWhere('p.localizationsID', 0);
            })
            ->orderByDesc('p.localizationsID')
            ->select([
                'p.productsID',
                'p.PaymentTypesDVID',
                'p.periodsOfValidityDVID',
                'p.numberOfPeriods',
                'p.unitsOfAccountDVID',
                'p.numberOfUnitsAccount',
                'p.unitPrice',
                'p.price',
                'p.vatRatesIK',
                'p.productName',
                'u.entryFee',
                'l.Default_VatRatesIK',
            ])
            ->first();

        if (!$product) {
            return null; // No entry fee product configured in the system.
        }

        return (array) $product;
    }
}