<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localization;
use Illuminate\Http\Request;

/**
 * Informacje prawne dla aplikacji: dane Usługodawcy (sprzedawcy) właściwego
 * dla Szkoły — wyświetlane przed każdym płatnym zamówieniem
 * (§ 2 ust. 7 Regulaminu aplikacji, część IX dokumentu prawnego).
 */
class LegalInfoController extends Controller
{
    public function serviceProvider(Request $request)
    {
        $localizationId = (int) $request->query('localization_id', 0);

        $providers = config('service_providers');
        $provider = $providers['by_localization'][$localizationId]
            ?? $providers['default'];

        $schoolName = null;
        $schoolAddress = null;
        if ($localizationId > 0) {
            $localization = Localization::where('LocalizationsID', $localizationId)
                ->first();
            if ($localization) {
                $schoolName = $localization->LocalizationName;
                $schoolAddress = trim(implode(', ', array_filter([
                    $localization->Address,
                    trim($localization->ZipCode . ' ' . $localization->City),
                ])));
            }
        }

        return response()->json([
            'success'  => true,
            'provider' => [
                'name'          => $provider['name'],
                'address'       => $provider['address'],
                'nip'           => $provider['nip'],
                'regon'         => $provider['regon'] ?? null,
                'schoolName'    => $schoolName,
                'schoolAddress' => $schoolAddress ?: null,
            ],
        ]);
    }
}
