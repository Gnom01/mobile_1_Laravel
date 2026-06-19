<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Wyznacza UsersID menadżerów szkoły, którzy mają dostać powiadomienie
 * o zmianie w harmonogramie / zgłoszeniu instruktora.
 *
 * Strategia:
 *  1) menadżerowie w tych samych lokalizacjach (LocalizationsID), co podane grupy,
 *     o stanowisku z config('instructor.manager_position_dvids');
 *  2) jeśli nie znamy DVID-ów stanowisk (pusta konfiguracja) lub nic nie znaleziono —
 *     awaryjnie config('instructor.fallback_manager_user_ids').
 *
 * Uwaga: brak w CRM jawnej flagi „menadżer" — patrz config/instructor.php
 * oraz ANALIZA_aplikacja_mobilna.md (sekcja braków CRM).
 */
final class SchoolManagerResolver
{
    /**
     * @param  array<int>  $coursesHeadingsIds  Grupy objęte zmianą/zgłoszeniem.
     * @param  array<int>  $excludeUserIds      Np. sam instruktor (nie powiadamiamy o własnym wpisie).
     * @return array<int>  UsersID menadżerów.
     */
    public static function forGroups(array $coursesHeadingsIds, array $excludeUserIds = []): array
    {
        $localizationIds = self::localizationIdsForGroups($coursesHeadingsIds);
        $positionDvids   = (array) config('instructor.manager_position_dvids', []);

        $managerUserIds = [];

        if (!empty($positionDvids)) {
            $query = DB::table('employees')
                ->whereIn('PositionsDVID', $positionDvids)
                ->where(function ($q) {
                    $q->whereNull('Cancelled')->orWhere('Cancelled', 0);
                })
                ->where('UsersID', '>', 0);

            // Jeśli znamy lokalizacje grup — zawężamy do tych szkół.
            if (!empty($localizationIds)) {
                $query->whereIn('LocalizationsID', $localizationIds);
            }

            $managerUserIds = $query->pluck('UsersID')->map(fn ($v) => (int) $v)->all();
        }

        // Fallback: jawnie skonfigurowani menadżerowie.
        if (empty($managerUserIds)) {
            $managerUserIds = (array) config('instructor.fallback_manager_user_ids', []);
        }

        $exclude = array_map('intval', $excludeUserIds);

        return array_values(array_unique(array_filter(
            array_map('intval', $managerUserIds),
            fn ($id) => $id > 0 && !in_array($id, $exclude, true)
        )));
    }

    /** LocalizationsID dla podanych grup (z tabeli courses). */
    private static function localizationIdsForGroups(array $coursesHeadingsIds): array
    {
        $coursesHeadingsIds = array_values(array_filter(array_map('intval', $coursesHeadingsIds)));
        if (empty($coursesHeadingsIds)) {
            return [];
        }

        return DB::table('courses')
            ->whereIn('coursesHeadingsID', $coursesHeadingsIds)
            ->where('localizationsID', '>', 0)
            ->distinct()
            ->pluck('localizationsID')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
