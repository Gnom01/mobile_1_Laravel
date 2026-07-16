<?php

/*
|--------------------------------------------------------------------------
| Rejestr Usługodawców (część IX dokumentu prawnego v6.1)
|--------------------------------------------------------------------------
| Sprzedawcą usługi jest podmiot prowadzący konkretną Szkołę. Aplikacja
| wyświetla pełną firmę, adres i NIP przed każdym płatnym zamówieniem
| (§ 2 ust. 7 Regulaminu aplikacji).
|
| "by_localization" nadpisuje wpis domyślny dla wskazanych LocalizationsID.
| TODO(biznes): uzupełnić przypisanie 13 lokalizacji do właściwych spółek
| po zatwierdzeniu rejestru (część X: "zatwierdzenie przypisania
| 13 lokalizacji do właściwych spółek").
*/

return [
    'default' => [
        'name'    => 'Egurrola Dance Studio – Agustin Marek Egurrola',
        'address' => 'ul. Marcina Kasprzaka 24a, 01-211 Warszawa',
        'nip'     => '5251340026',
        'regon'   => '016098446',
    ],

    'by_localization' => [
        // LocalizationsID => ['name' => ..., 'address' => ..., 'nip' => ..., 'regon' => ...],
    ],
];
