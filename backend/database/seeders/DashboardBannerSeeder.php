<?php

namespace Database\Seeders;

use App\Models\DashboardBanner;
use Illuminate\Database\Seeder;

class DashboardBannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            [
                'title' => 'LETNIE OBOZY 2026',
                'subtitle' => 'Zarezerwuj turnus juz teraz!',
                'description' => 'Profesjonalni instruktorzy, duzo tanca i niezapomniane wakacyjne wydarzenia.',
                'color_start' => '#C40233',
                'color_end' => '#E20613',
                'action_type' => DashboardBanner::ACTION_OFFERS,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'title' => 'NOWY SEZON WARSZTATOW',
                'subtitle' => 'YGM i European Workshops',
                'description' => 'Rozwijaj umiejetnosci pod okiem doswiadczonych instruktorow.',
                'color_start' => '#4A1481',
                'color_end' => '#7B2D8B',
                'action_type' => DashboardBanner::ACTION_OFFERS,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'title' => 'BILETY NA GALE I POKAZY',
                'subtitle' => 'Kup bilety w aplikacji',
                'description' => 'Sprawdz nadchodzace wydarzenia i zarezerwuj miejsce bezposrednio z pulpitu.',
                'color_start' => '#005C5C',
                'color_end' => '#0097A7',
                'action_type' => DashboardBanner::ACTION_OFFERS,
                'is_active' => true,
                'sort_order' => 30,
            ],
        ];

        foreach ($banners as $banner) {
            DashboardBanner::updateOrCreate(
                ['title' => $banner['title']],
                $banner
            );
        }
    }
}
