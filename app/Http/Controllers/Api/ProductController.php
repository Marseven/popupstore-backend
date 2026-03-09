<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List active products with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::active()
            ->with(['images', 'category']);

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        // Filter by collection
        if ($request->filled('collection')) {
            $query->whereHas('collection', fn($q) => $q->where('slug', $request->collection));
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
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->active()
            ->with(['images', 'stocks.size', 'category', 'collection', 'mediaContent'])
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Get featured products (limit 8).
     */
    public function featured(): JsonResponse
    {
        $products = Product::active()
            ->featured()
            ->with(['images', 'category'])
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

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
