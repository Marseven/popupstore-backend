<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRevenueShareRequest;
use App\Http\Requests\Admin\UpdateRevenueShareRequest;
use App\Models\Collection;
use App\Services\RevenueSplitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RevenueShareController extends Controller
{
    public function __construct(private RevenueSplitService $revenueSplit) {}

    public function index(int $collectionId): JsonResponse
    {
        $collection = Collection::findOrFail($collectionId);

        return response()->json([
            'revenue_shares' => $collection->revenueShares()->get(),
            'platform_percentage' => $this->platformShare($collection),
        ]);
    }

    public function store(StoreRevenueShareRequest $request, int $collectionId): JsonResponse
    {
        $collection = Collection::findOrFail($collectionId);

        $share = DB::transaction(function () use ($collection, $request) {
            $share = $collection->revenueShares()->create($request->validated());
            // Rejects (and rolls back) if total beneficiary share now exceeds 100%.
            $this->revenueSplit->validateShares($collection->fresh());

            return $share;
        });

        return response()->json(['revenue_share' => $share], 201);
    }

    public function update(UpdateRevenueShareRequest $request, int $collectionId, int $id): JsonResponse
    {
        $collection = Collection::findOrFail($collectionId);
        $share = $collection->revenueShares()->findOrFail($id);

        DB::transaction(function () use ($share, $collection, $request) {
            $share->update($request->validated());
            $this->revenueSplit->validateShares($collection->fresh());
        });

        return response()->json(['revenue_share' => $share->fresh()]);
    }

    public function destroy(int $collectionId, int $id): JsonResponse
    {
        $collection = Collection::findOrFail($collectionId);
        $collection->revenueShares()->findOrFail($id)->delete();

        return response()->json(['message' => 'Part supprimée']);
    }

    private function platformShare(Collection $collection): float
    {
        return round(100 - (float) $collection->revenueShares()->sum('percentage'), 2);
    }
}
