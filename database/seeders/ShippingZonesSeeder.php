<?php

namespace Database\Seeders;

use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            [
                'name' => 'Libreville',
                'sort_order' => 1,
                'zones' => [
                    ['name' => 'Centre-ville', 'fee' => 1000, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 1500, 'sort_order' => 2],
                    ['name' => 'Banlieue', 'fee' => 2000, 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Owendo',
                'sort_order' => 2,
                'zones' => [
                    ['name' => 'Centre', 'fee' => 1500, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 2000, 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Akanda',
                'sort_order' => 3,
                'zones' => [
                    ['name' => 'Centre', 'fee' => 1500, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 2000, 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Port-Gentil',
                'sort_order' => 4,
                'zones' => [
                    ['name' => 'Centre', 'fee' => 2500, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 3000, 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Franceville',
                'sort_order' => 5,
                'zones' => [
                    ['name' => 'Centre', 'fee' => 3000, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 3500, 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'Oyem',
                'sort_order' => 6,
                'zones' => [
                    ['name' => 'Centre', 'fee' => 3000, 'sort_order' => 1],
                    ['name' => 'Périphérie', 'fee' => 3500, 'sort_order' => 2],
                ],
            ],
        ];

        foreach ($cities as $cityData) {
            $zones = $cityData['zones'];
            unset($cityData['zones']);

            $city = ShippingCity::updateOrCreate(
                ['name' => $cityData['name']],
                $cityData
            );

            foreach ($zones as $zoneData) {
                ShippingZone::updateOrCreate(
                    ['shipping_city_id' => $city->id, 'name' => $zoneData['name']],
                    $zoneData
                );
            }
        }
    }
}
