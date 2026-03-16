<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingController extends Controller
{
    /**
     * Get active shipping zones with their cities.
     */
    public function cities(): JsonResponse
    {
        $data = Cache::remember('shipping.checkout_data', 600, function () {
            $zones = ShippingZone::active()
                ->with(['cities' => fn($q) => $q->active()->orderBy('name')])
                ->get();

            $freeThreshold = Setting::get('free_shipping_threshold', 0);

            return [
                'zones' => $zones,
                'free_shipping_threshold' => (float) $freeThreshold,
                'payment_methods' => [
                    'ebilling' => Setting::get('payment_ebilling_active', '1') === '1',
                    'cod' => Setting::get('payment_cod_active', '0') === '1',
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Calculate shipping fee for a given city.
     */
    public function calculateFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => 'required|string|max:100',
        ]);

        $fee = ShippingCity::getShippingFee($validated['city']);

        if ($fee === null) {
            return response()->json([
                'message' => 'Ville non trouvée dans nos zones de livraison',
            ], 422);
        }

        $freeThreshold = (float) Setting::get('free_shipping_threshold', 0);

        $city = ShippingCity::active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['city'])])
            ->with('zone')
            ->first();

        return response()->json([
            'fee' => $fee,
            'zone_name' => $city->zone->name,
            'free_threshold' => $freeThreshold,
        ]);
    }
}
