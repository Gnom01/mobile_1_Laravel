<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Menadżer szkoły (odbiorca powiadomień o zmianach i zgłoszeniach)
    |--------------------------------------------------------------------------
    |
    | CRM nie ma jawnej flagi „menadżer szkoły". Identyfikujemy go po stanowisku
    | (employees.PositionsDVID) w obrębie tej samej lokalizacji (LocalizationsID),
    | co grupa objęta zmianą.
    |
    | UWAGA (brak danych z CRM): trzeba podać realne wartości PositionsDVID
    | odpowiadające kierownikowi/menadżerowi szkoły. Do czasu ustalenia można
    | wpisać konkretne UsersID w `fallback_user_ids`, żeby powiadomienia działały.
    | Patrz ANALIZA_aplikacja_mobilna.md (sekcja braków CRM).
    |
    */

    // PositionsDVID uznawane za menadżera/kierownika szkoły.
    'manager_position_dvids' => array_filter(array_map(
        'intval',
        explode(',', (string) env('INSTRUCTOR_MANAGER_POSITION_DVIDS', ''))
    )),

    // Awaryjna, jawna lista UsersID menadżerów (gdy nie znamy jeszcze DVID-ów).
    'fallback_manager_user_ids' => array_filter(array_map(
        'intval',
        explode(',', (string) env('INSTRUCTOR_MANAGER_USER_IDS', ''))
    )),

    /*
    |--------------------------------------------------------------------------
    | Słownik typów zmian w harmonogramie (multiselect w kreatorze „+")
    |--------------------------------------------------------------------------
    | Docelowo może pochodzić ze słownika CRM. Na razie lista statyczna.
    */
    'change_types' => [
        ['key' => 'cancellation', 'label' => 'Odwołanie zajęć'],
        ['key' => 'reschedule',   'label' => 'Przełożenie zajęć'],
        ['key' => 'location',     'label' => 'Zmiana sali / lokalizacji'],
        ['key' => 'time',         'label' => 'Zmiana godziny'],
        ['key' => 'substitution', 'label' => 'Zastępstwo instruktora'],
        ['key' => 'extra',        'label' => 'Zajęcia dodatkowe'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Słownik typów zgłoszeń instruktora
    |--------------------------------------------------------------------------
    */
    'report_types' => [
        ['key' => 'facility',     'label' => 'Usterka sali / sprzętu'],
        ['key' => 'equipment',    'label' => 'Brak / prośba o sprzęt'],
        ['key' => 'substitution', 'label' => 'Prośba o zastępstwo'],
        ['key' => 'leave',        'label' => 'Wniosek urlopowy / nieobecność'],
        ['key' => 'participant',  'label' => 'Sprawa uczestnika'],
        ['key' => 'other',        'label' => 'Inne'],
    ],
];
