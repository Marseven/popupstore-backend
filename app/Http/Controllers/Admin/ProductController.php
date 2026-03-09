<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Paginated list of products with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['images', 'stocks.size', 'category', 'collection']);

        // Search by name, SKU, or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min($request->get('per_page', 15), 50);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Create a new product with images and stock entries.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'media_content_id' => 'nullable|integer|exists:media_contents,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|max:5120',
            'primary_image_index' => 'nullable|integer|min:0',
            'stocks' => 'nullable|array',
            'stocks.*.size_id' => 'required_with:stocks|integer|exists:sizes,id',
            'stocks.*.quantity' => 'required_with:stocks|integer|min:0',
            'stocks.*.low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        try {
            $product = DB::transaction(function () use ($validated, $request) {
                // Generate slug
                $validated['slug'] = Str::slug($validated['name']);

                // Ensure unique slug
                $slugCount = Product::where('slug', $validated['slug'])->count();
                if ($slugCount > 0) {
                    $validated['slug'] .= '-' . ($slugCount + 1);
                }

                $product = Product::create(collect($validated)->except(['images', 'primary_image_index', 'stocks'])->toArray());

                // Handle image uploads
                if ($request->hasFile('images')) {
                    $primaryIndex = $validated['primary_image_index'] ?? 0;

                    foreach ($request->file('images') as $index => $imageFile) {
                        $path = $imageFile->store('products', 'public');

                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'alt_text' => $product->name,
                            'is_primary' => $index === $primaryIndex,
                            'sort_order' => $index,
                        ]);
                    }
                }

                // Create stock entries
                if (!empty($validated['stocks'])) {
                    foreach ($validated['stocks'] as $stockData) {
                        ProductStock::create([
                            'product_id' => $product->id,
                            'size_id' => $stockData['size_id'],
                            'quantity' => $stockData['quantity'],
                            'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                        ]);
                    }
                }

                return $product;
            });

            return response()->json([
                'message' => 'Produit créé avec succès',
                'product' => $product->load(['images', 'stocks.size', 'category', 'collection']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du produit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get product with all relations.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['images', 'stocks.size', 'category', 'collection', 'mediaContent'])
            ->findOrFail($id);

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $product->id,
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'media_content_id' => 'nullable|integer|exists:media_contents,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'primary_image_index' => 'nullable|integer|min:0',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer|exists:product_images,id',
        ]);

        // Check max 4 images: (existing - removed + new) <= 4
        $existingCount = $product->images()->count();
        $removedCount = count($validated['remove_image_ids'] ?? []);
        $newCount = $request->hasFile('images') ? count($request->file('images')) : 0;

        if (($existingCount - $removedCount + $newCount) > 4) {
            return response()->json([
                'message' => 'Un produit ne peut pas avoir plus de 4 images.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated, $request, $product) {
                // Update slug if name changed
                if (isset($validated['name']) && $validated['name'] !== $product->name) {
                    $slug = Str::slug($validated['name']);
                    $slugCount = Product::where('slug', $slug)->where('id', '!=', $product->id)->count();
                    if ($slugCount > 0) {
                        $slug .= '-' . ($slugCount + 1);
                    }
                    $validated['slug'] = $slug;
                }

                $product->update(collect($validated)->except(['images', 'primary_image_index', 'remove_image_ids'])->toArray());

                // Remove specified images
                if (!empty($validated['remove_image_ids'])) {
                    $imagesToRemove = ProductImage::where('product_id', $product->id)
                        ->whereIn('id', $validated['remove_image_ids'])
                        ->get();

                    foreach ($imagesToRemove as $image) {
                        Storage::disk('public')->delete($image->path);
                        $image->delete();
                    }
                }

                // Handle new image uploads
                if ($request->hasFile('images')) {
                    $existingCount = $product->images()->count();
                    $primaryIndex = $validated['primary_image_index'] ?? null;

                    foreach ($request->file('images') as $index => $imageFile) {
                        $path = $imageFile->store('products', 'public');

                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'alt_text' => $product->name,
                            'is_primary' => $primaryIndex !== null && $index === $primaryIndex,
                            'sort_order' => $existingCount + $index,
                        ]);
                    }

                    // If primary image was set, unset others
                    if ($primaryIndex !== null) {
                        $product->images()
                            ->where('sort_order', '!=', $existingCount + $primaryIndex)
                            ->update(['is_primary' => false]);
                    }
                }
            });

            return response()->json([
                'message' => 'Produit mis à jour avec succès',
                'product' => $product->fresh()->load(['images', 'stocks.size', 'category', 'collection']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du produit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a product (soft check for orders first).
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // Check if product has order items
        $orderCount = $product->orderItems()->count();

        if ($orderCount > 0) {
            return response()->json([
                'message' => 'Ce produit est lié à ' . $orderCount . ' commande(s). Voulez-vous le désactiver plutôt que le supprimer ?',
                'has_orders' => true,
                'order_count' => $orderCount,
            ], 409);
        }

        // Delete images from storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        // Delete related records
        $product->images()->delete();
        $product->stocks()->delete();
        $product->cartItems()->delete();
        $product->delete();

        return response()->json([
            'message' => 'Produit supprimé avec succès',
        ]);
    }

    /**
     * Update stock for a product.
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'stocks' => 'required|array',
            'stocks.*.size_id' => 'required|integer|exists:sizes,id',
            'stocks.*.quantity' => 'required|integer|min:0',
            'stocks.*.low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        foreach ($validated['stocks'] as $stockData) {
            ProductStock::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'size_id' => $stockData['size_id'],
                ],
                [
                    'quantity' => $stockData['quantity'],
                    'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                ]
            );
        }

        return response()->json([
            'message' => 'Stock mis à jour avec succès',
            'stocks' => $product->stocks()->with('size')->get(),
        ]);
    }
}
