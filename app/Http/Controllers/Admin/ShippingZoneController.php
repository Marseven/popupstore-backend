<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = ShippingZone::withCount('cities')
            ->orderBy('sort_order')
            ->paginate(15);

        return response()->json($zones);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'cities' => 'array',
            'cities.*' => 'string|max:100',
        ]);

        $cities = $validated['cities'] ?? [];
        unset($validated['cities']);

        $zone = ShippingZone::create($validated);

        foreach ($cities as $cityName) {
            $cityName = trim($cityName);
            if ($cityName) {
                $zone->cities()->create(['name' => $cityName]);
            }
        }

        Cache::forget('shipping.cities');

        return response()->json([
            'message' => 'Zone créée avec succès',
            'zone' => $zone->load('cities'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $zone = ShippingZone::with('cities')->findOrFail($id);

        return response()->json(['zone' => $zone]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'cities' => 'array',
            'cities.*' => 'string|max:100',
        ]);

        $cities = $validated['cities'] ?? [];
        unset($validated['cities']);

        $zone->update($validated);

        // Sync cities: delete all, recreate
        $zone->cities()->delete();
        foreach ($cities as $cityName) {
            $cityName = trim($cityName);
            if ($cityName) {
                $zone->cities()->create(['name' => $cityName]);
            }
        }

        Cache::forget('shipping.cities');

        return response()->json([
            'message' => 'Zone mise à jour',
            'zone' => $zone->load('cities'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->delete();

        Cache::forget('shipping.cities');

        return response()->json(['message' => 'Zone supprimée']);
    }
}
