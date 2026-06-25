<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rozmiary koszulek (obozy)
    |--------------------------------------------------------------------------
    | CRM nie udostępnia słownika rozmiarów koszulek, więc lista jest statyczna
    | (jak w portalu). Serwowana przez DictionaryController::getCampDictionaries
    | pod kluczem `tshirtSizes`.
    */
    'tshirt_sizes' => [
        '134-140 cm',
        '146-152 cm',
        '158-164 cm',
        'XS',
        'S',
        'M',
        'L',
        'XL',
    ],
];
