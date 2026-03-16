<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $cities = ShippingCity::with(['zones' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('zones')
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json($cities);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'zones' => 'required|array|min:1',
            'zones.*.name' => 'required|string|max:100',
            'zones.*.fee' => 'required|numeric|min:0',
            'zones.*.is_active' => 'boolean',
            'zones.*.sort_order' => 'integer|min:0',
        ]);

        $zonesData = $validated['zones'];
        unset($validated['zones']);

        $city = ShippingCity::create($validated);

        foreach ($zonesData as $zoneData) {
            $city->zones()->create($zoneData);
        }

        Cache::forget('shipping.checkout_data');

        return response()->json([
            'message' => 'Ville créée avec succès',
            'city' => $city->load('zones'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $city = ShippingCity::with(['zones' => fn ($q) => $q->orderBy('sort_order')])->findOrFail($id);

        return response()->json(['city' => $city]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $city = ShippingCity::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'zones' => 'required|array|min:1',
            'zones.*.name' => 'required|string|max:100',
            'zones.*.fee' => 'required|numeric|min:0',
            'zones.*.is_active' => 'boolean',
            'zones.*.sort_order' => 'integer|min:0',
        ]);

        $zonesData = $validated['zones'];
        unset($validated['zones']);

        $city->update($validated);

        // Sync zones: delete all, recreate
        $city->zones()->delete();
        foreach ($zonesData as $zoneData) {
            $city->zones()->create($zoneData);
        }

        Cache::forget('shipping.checkout_data');

        return response()->json([
            'message' => 'Ville mise à jour',
            'city' => $city->load('zones'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $city = ShippingCity::findOrFail($id);
        $city->delete();

        Cache::forget('shipping.checkout_data');

        return response()->json(['message' => 'Ville supprimée']);
    }
}
