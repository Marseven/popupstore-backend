<?php

namespace Database\Seeders;

use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Libreville & environs',
                'fee' => 1000,
                'sort_order' => 1,
                'cities' => ['Libreville', 'Owendo', 'Akanda', 'Ntoum'],
            ],
            [
                'name' => 'Estuaire élargi',
                'fee' => 1500,
                'sort_order' => 2,
                'cities' => ['Kango', 'Cocobeach', 'Cap Estérias'],
            ],
            [
                'name' => 'Port-Gentil & Ouest',
                'fee' => 2500,
                'sort_order' => 3,
                'cities' => ['Port-Gentil', 'Lambaréné', 'Gamba', 'Omboué', 'Mouila'],
            ],
            [
                'name' => 'Nord',
                'fee' => 3000,
                'sort_order' => 4,
                'cities' => ['Oyem', 'Bitam', 'Mitzic', 'Makokou', 'Ovan'],
            ],
            [
                'name' => 'Sud & Est',
                'fee' => 3000,
                'sort_order' => 5,
                'cities' => ['Franceville', 'Moanda', 'Lékoni', 'Koulamoutou', 'Tchibanga', 'Mayumba'],
            ],
        ];

        foreach ($zones as $zoneData) {
            $cities = $zoneData['cities'];
            unset($zoneData['cities']);

            $zone = ShippingZone::updateOrCreate(
                ['name' => $zoneData['name']],
                $zoneData
            );

            foreach ($cities as $cityName) {
                ShippingCity::updateOrCreate(
                    ['name' => $cityName],
                    ['shipping_zone_id' => $zone->id]
                );
            }
        }
    }
}
