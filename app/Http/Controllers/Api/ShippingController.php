<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShippingCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ShippingController extends Controller
{
    /**
     * Get active shipping cities with their zones.
     */
    public function cities(): JsonResponse
    {
        $data = Cache::remember('shipping.checkout_data', 600, function () {
            $cities = ShippingCity::active()
                ->with(['zones' => fn ($q) => $q->active()->orderBy('sort_order')])
                ->get();

            $freeThreshold = Setting::get('free_shipping_threshold', 0);

            return [
                'cities' => $cities,
                'free_shipping_threshold' => (float) $freeThreshold,
                'payment_methods' => [
                    'ebilling' => Setting::get('payment_ebilling_active', '1') === '1',
                    'cod' => Setting::get('payment_cod_active', '0') === '1',
                ],
            ];
        });

        return response()->json($data);
    }
}
