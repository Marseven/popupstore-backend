<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * List active products with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::active()
            ->visibleTo($request->user())
            ->with(['images', 'category']);

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        // Filter by collection
        if ($request->filled('collection')) {
            $query->whereHas('collection', fn ($q) => $q->where('slug', $request->collection));
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->featured();
        }

        // Search by name, description, or SKU
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['name', 'price', 'created_at', 'sort_order'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min($request->get('per_page', 15), 50);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Get a single product by slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->active()
            ->visibleTo($request->user())
            ->with(['images', 'stocks.size', 'category', 'collection', 'mediaContent'])
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Public media hub for a product — the media unlocked by its QR code.
     */
    public function media(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->active()->firstOrFail();

        $media = $product->mediaContents()
            ->get(['media_contents.id', 'uuid', 'title', 'type', 'duration'])
            ->map(fn ($m) => [
                'uuid' => $m->uuid,
                'title' => $m->title,
                'type' => $m->type,
                'duration' => $m->duration,
            ]);

        return response()->json([
            'product' => ['name' => $product->name, 'slug' => $product->slug],
            'media' => $media,
        ]);
    }

    /**
     * Get featured products (limit 8).
     */
    public function featured(Request $request): JsonResponse
    {
        // Separate cache entries: guests must never be served a secret product.
        $user = $request->user();
        $cacheKey = $user ? 'products.featured.auth' : 'products.featured.public';

        $products = Cache::remember($cacheKey, 300, function () use ($user) {
            $featured = Product::active()
                ->featured()
                ->visibleTo($user)
                ->with(['images', 'category'])
                ->orderBy('sort_order')
                ->limit(8)
                ->get();

            // Fallback: if there aren't enough featured products, top up with the
            // most recent products so the section is never sparse.
            if ($featured->count() < 5) {
                $fill = Product::active()
                    ->visibleTo($user)
                    ->whereNotIn('id', $featured->pluck('id'))
                    ->with(['images', 'category'])
                    ->latest()
                    ->limit(5 - $featured->count())
                    ->get();

                $featured = $featured->concat($fill);
            }

            return $featured->values();
        });

        return response()->json([
            'products' => $products,
        ]);
    }

    /**
     * Get products by category slug, paginated.
     */
    public function byCategory(string $slug, Request $request): JsonResponse
    {
        $category = ProductCategory::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $perPage = min($request->get('per_page', 15), 50);

        $products = Product::active()
            ->visibleTo($request->user())
            ->where('category_id', $category->id)
            ->with(['images', 'category'])
            ->orderBy('sort_order')
            ->paginate($perPage);

        return response()->json([
            'category' => $category,
            'products' => $products,
        ]);
    }

    /**
     * Get products by collection slug, paginated.
     */
    public function byCollection(string $slug, Request $request): JsonResponse
    {
        $collection = Collection::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $perPage = min($request->get('per_page', 15), 50);

        $products = Product::active()
            ->visibleTo($request->user())
            ->where('collection_id', $collection->id)
            ->with(['images', 'category'])
            ->orderBy('sort_order')
            ->paginate($perPage);

        return response()->json([
            'collection' => $collection,
            'products' => $products,
        ]);
    }
}
