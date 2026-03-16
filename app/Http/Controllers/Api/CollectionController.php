<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CollectionController extends Controller
{
    /**
     * List active collections ordered by sort_order, with counts of media and products.
     */
    public function index(): JsonResponse
    {
        $collections = Cache::remember('collections.active', 300, function () {
            return Collection::active()
                ->withCount(['mediaContents', 'products'])
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json([
            'collections' => $collections,
        ]);
    }

    /**
     * Get collection by slug with media contents and products (paginated).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $collection = Collection::where('slug', $slug)
            ->active()
            ->withCount(['mediaContents', 'products'])
            ->firstOrFail();

        $perPage = min($request->get('per_page', 15), 50);

        $mediaContents = $collection->mediaContents()
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'media_page');

        $products = $collection->products()
            ->active()
            ->with(['images', 'category'])
            ->orderBy('sort_order')
            ->paginate($perPage, ['*'], 'products_page');

        return response()->json([
            'collection' => $collection,
            'media_contents' => $mediaContents,
            'products' => $products,
        ]);
    }
}
