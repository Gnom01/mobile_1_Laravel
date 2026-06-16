<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\UsersRelation;

/**
 * Wyznacza rolę użytkownika dla aplikacji mobilnej zamiast zaszytego na sztywno
 * `role => 2`. Zwraca tablicę ról (Flutter obsługuje List<int>):
 *   1 = dziecko, 2 = rodzic/opiekun, 3 = instruktor.
 *
 * Reguły:
 *  - instruktor: konto powiązane z rekordem w `employees`,
 *  - SMS: jawny kontekst relacji ('family' → dziecko, 'self' → rodzic),
 *  - logowanie hasłem (brak kontekstu): wyznacz z `users_relations`
 *    (ma dzieci pod sobą → rodzic; sam jest czyimś dzieckiem → dziecko).
 */
final class RoleResolver
{
    /**
     * @param  object       $user          CrmUser (musi mieć UsersID).
     * @param  string|null  $relationship  'self' | 'family' (kontekst SMS) lub null.
     * @return array<int>
     */
    public static function resolve($user, ?string $relationship = null): array
    {
        $usersId = (int) ($user->UsersID ?? 0);
        if ($usersId <= 0) {
            return [2];
        }

        // Instruktor ma pierwszeństwo — niezależnie od kontekstu logowania.
        if (self::isEmployee($usersId)) {
            return [3];
        }

        // Kontekst SMS: jawnie wiemy, czy to właściciel telefonu, czy podopieczny.
        if ($relationship === 'family') {
            return [1];
        }
        if ($relationship === 'self') {
            return [2];
        }

        // Logowanie hasłem — wyznacz z relacji rodzic/dziecko.
        try {
            $hasChildren = UsersRelation::where('Parent_UsersID', $usersId)
                ->where('Cancelled', 0)
                ->exists();
            if ($hasChildren) {
                return [2];
            }

            $isSomeonesChild = UsersRelation::where('UsersID', $usersId)
                ->where('Cancelled', 0)
                ->exists();
            if ($isSomeonesChild) {
                return [1];
            }
        } catch (\Throwable $e) {
            // brak tabeli / błąd → bezpieczny domyślny rodzic
        }

        return [2];
    }

    private static function isEmployee(int $usersId): bool
    {
        try {
            return Employee::where('UsersID', $usersId)
                ->where(function ($q) {
                    $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
