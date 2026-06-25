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

        // Kontekst SMS „family" — podopieczny zawsze jako dziecko.
        if ($relationship === 'family') {
            return [1];
        }

        $isEmployee = self::isEmployee($usersId);
        $hasChildren = self::hasChildren($usersId);
        $isChild = self::isSomeonesChild($usersId);

        // Budujemy listę ról z priorytetem: RODZIC przed instruktorem.
        // Dzięki temu konto, które jest jednocześnie rodzicem i pracownikiem
        // (np. trener mający własne dzieci), domyślnie widzi panel rodzica
        // (z Płatnościami i Ofertą), a rola instruktora jest dostępna jako druga.
        $roles = [];
        if ($hasChildren) {
            $roles[] = 2; // rodzic / opiekun
        }
        if ($isEmployee) {
            $roles[] = 3; // instruktor
        }

        if (empty($roles)) {
            if ($relationship === 'self') {
                $roles[] = 2; // właściciel telefonu bez dzieci/etatu → rodzic/dorosły
            } elseif ($isChild) {
                $roles[] = 1; // sam jest czyimś dzieckiem
            } else {
                $roles[] = 2; // bezpieczny domyślny
            }
        }

        return $roles;
    }

    private static function hasChildren(int $usersId): bool
    {
        try {
            return UsersRelation::where('Parent_UsersID', $usersId)
                ->where('Cancelled', 0)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function isSomeonesChild(int $usersId): bool
    {
        try {
            return UsersRelation::where('UsersID', $usersId)
                ->where('Cancelled', 0)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
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
