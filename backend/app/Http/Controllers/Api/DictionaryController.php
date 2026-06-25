<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DictionaryController extends Controller
{
    /**
     * Get dictionary items, optionally filtered by name.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Dictionary::query()->where('Cancelled', 0);

        if ($request->has('dictionaryName')) {
            $query->where('DictionaryName', $request->get('dictionaryName'));
        }

        if ($request->has('parentDictionaryName')) {
            $query->where('Parent_DictionaryName', $request->get('parentDictionaryName'));
        }

        $dictionaries = $query->orderBy('DictionaryName')
            ->orderBy('OrderPosition')
            ->get();

        return response()->json($dictionaries);
    }

    /**
     * Get all course-related dictionaries grouped by key.
     * Used by the mobile app for filter/form population.
     *
     * GET /dictionaries/courses
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCoursesDictionaries()
    {
        // Map: response key => DictionaryName in DB
        $groups = [
            'CourseFrequency'         => 'CourseFrequency',
            'courseDurationInMinutes' => 'DurationInMinutes',
            'websiteStatuses'         => 'WebsiteStatuses',
            'courseAgeRanges'         => 'Dimension/Grupy wiekowe',
            'courseDanceStyle'        => 'Dimension/Style taneczne',
            'courseLevel'             => 'Dimension/Poziomy zaawansowania',
            'localizations'           => 'Dimension/Lokalizacje',
            'WeekDays'                => 'WeekDays',
            'mainCategory'            => 'Dimension/Kategoria główna',
        ];

        $dictionaryNames = array_values($groups);

        $rows = Dictionary::query()
            ->where('Cancelled', 0)
            ->whereIn('DictionaryName', $dictionaryNames)
            ->orderBy('Name')
            ->get();

        // Map DB row to camelCase response shape
        $mapRow = fn($item) => [
            'dictionariesID'       => $item->DictionariesID,
            'parent_DictionariesID'=> $item->Parent_DictionariesID,
            'dictionaryName'       => $item->DictionaryName,
            'parent_valueID'       => $item->Parent_ValueID,
            'name'                 => $item->Name,
            'valueID'              => $item->ValueID,
            'valueText'            => $item->ValueText,
            'orderPosition'        => $item->OrderPosition,
            'description'          => $item->Description,
            'editable'             => $item->Editable,
            'itemColor'            => $item->ItemColor,
        ];

        $result = [];
        foreach ($groups as $key => $dictionaryName) {
            $result[$key] = $rows
                ->where('DictionaryName', $dictionaryName)
                ->values()
                ->map($mapRow)
                ->values()
                ->all();
        }

        // employeesID: employees table not yet synced — return empty array as placeholder
        $result['employeesID'] = $this->getEmployees();

        return response()->json([
            'status'      => '200',
            'body'        => $result,
            'recordCount' => 0,
        ]);
    }

    /**
     * Get all camp / day-camp related dimensions grouped by key.
     * Used by the mobile app to populate the camp enrollment wizard
     * (age ranges, dance styles, diets, locations, t-shirt sizes).
     *
     * Wymiary pochodzą z już zsynchronizowanej tabeli `dictionaries`
     * (PullDictionariesJob → /CrmToMobileSync/getDictionariesMobile).
     * Rozmiary koszulek nie mają słownika w CRM — zwracamy listę statyczną
     * (jak w portalu). Turnus = sama oferta obozu (coursesHeadingsID).
     *
     * GET /dictionaries/camps
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCampDictionaries()
    {
        // Map: response key => DictionaryName in DB
        $groups = [
            'courseAgeRanges'  => 'Dimension/Grupy wiekowe',
            'courseDanceStyle' => 'Dimension/Style taneczne',
            'localizations'    => 'Dimension/Lokalizacje',
            'diet'             => 'dietCamp',
            'season'           => 'productsLevel3',
        ];

        $dictionaryNames = array_values($groups);

        $rows = Dictionary::query()
            ->where('Cancelled', 0)
            ->whereIn('DictionaryName', $dictionaryNames)
            ->orderBy('OrderPosition')
            ->orderBy('Name')
            ->get();

        $mapRow = fn($item) => [
            'dictionariesID'        => $item->DictionariesID,
            'parent_DictionariesID' => $item->Parent_DictionariesID,
            'dictionaryName'        => $item->DictionaryName,
            'parent_valueID'        => $item->Parent_ValueID,
            'name'                  => $item->Name,
            'valueID'               => $item->ValueID,
            'valueText'             => $item->ValueText,
            'orderPosition'         => $item->OrderPosition,
            'description'           => $item->Description,
            'editable'              => $item->Editable,
            'itemColor'             => $item->ItemColor,
        ];

        $result = [];
        foreach ($groups as $key => $dictionaryName) {
            $result[$key] = $rows
                ->where('DictionaryName', $dictionaryName)
                ->values()
                ->map($mapRow)
                ->values()
                ->all();
        }

        // Rozmiary koszulek — brak słownika w CRM, lista statyczna (jak w portalu).
        $tshirtSizes = config('camps.tshirt_sizes', [
            '134-140 cm', '146-152 cm', '158-164 cm',
            'XS', 'S', 'M', 'L', 'XL',
        ]);
        $result['tshirtSizes'] = [];
        foreach (array_values($tshirtSizes) as $idx => $size) {
            $result['tshirtSizes'][] = [
                'name'          => $size,
                'valueID'       => $size,
                'orderPosition' => $idx,
            ];
        }

        return response()->json([
            'status'      => '200',
            'body'        => $result,
            'recordCount' => 0,
        ]);
    }

    /**
     * Fetch employees from the crm_users / users table.
     * Falls back to an empty array when the employees table does not yet exist.
     *
     * @return array
     */
    private function getEmployees(): array
    {
        try {
            $rows = DB::table('employees as e')
                ->select(
                    'e.employeesID',
                    'u.UsersID as usersID',
                    'u.fullName',
                    'e.description',
                    'e.fileExtension',
                    'e.fileName'
                )
                ->leftJoin('users as u', 'u.UsersID', '=', 'e.usersID')
                ->where('e.cancelled', 0)
                ->orderBy('e.employeesID')
                ->get();

            return $rows->map(fn($r) => [
                'description'         => $r->description ?? '',
                'employeesID'         => $r->employeesID,
                'stylesDIDArray'      => [],   // styles join not yet implemented
                'fullName'            => $r->fullName ?? '',
                'fileExtension'       => $r->fileExtension ?? '',
                'fileName'            => $r->fileName ?? '',
                'usersID'             => $r->usersID,
                'dictionariesID'      => 0,
                'parent_DictionariesID' => 0,
                'dictionaryName'      => '',
                'parent_valueID'      => 0,
                'name'                => '',
                'valueID'             => 0,
                'valueText'           => '',
                'orderPosition'       => 0,
                'editable'            => 0,
                'itemColor'           => '',
                'fileURL'             => $r->fileName
                    ? url("assets/users/{$r->usersID}/{$r->fileName}_small.{$r->fileExtension}")
                    : url('assets/img/woman.png'),
            ])->all();
        } catch (\Exception $e) {
            // employees table not yet created — return empty mock
            return [];
        }
    }
}