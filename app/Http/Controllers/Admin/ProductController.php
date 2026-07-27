<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Notifications\LowStockNotification;
use App\Services\NotificationService;
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
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $product = DB::transaction(function () use ($validated, $request) {
                // Generate slug
                $validated['slug'] = Str::slug($validated['name']);

                // Ensure unique slug
                $slugCount = Product::where('slug', $validated['slug'])->count();
                if ($slugCount > 0) {
                    $validated['slug'] .= '-'.($slugCount + 1);
                }

                $product = Product::create(collect($validated)->except(['images', 'primary_image_index', 'stocks', 'media_content_ids'])->toArray());

                // Media contents unlocked by the product QR (audio + video)
                if (array_key_exists('media_content_ids', $validated)) {
                    $product->mediaContents()->sync($this->pivotOrder($validated['media_content_ids'] ?? []));
                }

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
                if (! empty($validated['stocks'])) {
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
        $product = Product::with(['images', 'stocks.size', 'category', 'collection', 'mediaContent', 'mediaContents'])
            ->findOrFail($id);

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * Update a product.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validated();

        // The form posts `existing_images` = the IDs to KEEP (JSON string). When
        // present, every other image is removed. Absent → keep all (no change).
        $keptIds = null;
        if ($request->has('existing_images')) {
            $decoded = json_decode((string) $request->input('existing_images'), true);
            $keptIds = is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        // Max 4 images: kept + new <= 4.
        $keptCount = $keptIds !== null ? count($keptIds) : $product->images()->count();
        $newCount = $request->hasFile('images') ? count($request->file('images')) : 0;

        if (($keptCount + $newCount) > 4) {
            return response()->json([
                'message' => 'Un produit ne peut pas avoir plus de 4 images.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated, $request, $product, $keptIds) {
                // Update slug if name changed
                if (isset($validated['name']) && $validated['name'] !== $product->name) {
                    $slug = Str::slug($validated['name']);
                    $slugCount = Product::where('slug', $slug)->where('id', '!=', $product->id)->count();
                    if ($slugCount > 0) {
                        $slug .= '-'.($slugCount + 1);
                    }
                    $validated['slug'] = $slug;
                }

                $product->update(collect($validated)->except(['images', 'primary_image_index', 'remove_image_ids', 'media_content_ids'])->toArray());

                if (array_key_exists('media_content_ids', $validated)) {
                    $product->mediaContents()->sync($this->pivotOrder($validated['media_content_ids'] ?? []));
                }

                // Remove images the user dropped, and reorder the ones they kept.
                if ($keptIds !== null) {
                    $toRemove = ProductImage::where('product_id', $product->id)
                        ->whereNotIn('id', $keptIds)
                        ->get();

                    foreach ($toRemove as $image) {
                        Storage::disk('public')->delete($image->path);
                        $image->delete();
                    }

                    foreach (array_values($keptIds) as $order => $imgId) {
                        ProductImage::where('product_id', $product->id)
                            ->where('id', $imgId)
                            ->update(['sort_order' => $order]);
                    }
                }

                // Handle new image uploads (appended after the kept ones).
                if ($request->hasFile('images')) {
                    $base = $product->images()->count();
                    $primaryIndex = $validated['primary_image_index'] ?? null;

                    foreach ($request->file('images') as $index => $imageFile) {
                        $path = $imageFile->store('products', 'public');

                        ProductImage::create([
                            'product_id' => $product->id,
                            'path' => $path,
                            'alt_text' => $product->name,
                            'is_primary' => $primaryIndex !== null && $index === $primaryIndex,
                            'sort_order' => $base + $index,
                        ]);
                    }

                    if ($primaryIndex !== null) {
                        $product->images()
                            ->where('sort_order', '!=', $base + $primaryIndex)
                            ->update(['is_primary' => false]);
                    }
                }

                // Guarantee at least one primary image remains after edits.
                if ($product->images()->where('is_primary', true)->count() === 0) {
                    $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
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
                'message' => 'Ce produit est lié à '.$orderCount.' commande(s). Voulez-vous le désactiver plutôt que le supprimer ?',
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

        $notificationService = app(NotificationService::class);

        foreach ($validated['stocks'] as $stockData) {
            $stock = ProductStock::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'size_id' => $stockData['size_id'],
                ],
                [
                    'quantity' => $stockData['quantity'],
                    'low_stock_threshold' => $stockData['low_stock_threshold'] ?? 5,
                ]
            );

            if ($stock->is_low_stock) {
                $sizeName = $stock->size?->name ?? 'N/A';
                $notificationService->notifyAdmins(
                    new LowStockNotification($product, $sizeName, $stock->quantity)
                );
            }
        }

        return response()->json([
            'message' => 'Stock mis à jour avec succès',
            'stocks' => $product->stocks()->with('size')->get(),
        ]);
    }

    /**
     * Download a single QR code that unlocks all of the product's media
     * (the /unlock/{slug} hub page).
     */
    public function downloadQr(int $id)
    {
        $product = Product::findOrFail($id);

        $url = rtrim(config('app.frontend_url'), '/').'/unlock/'.$product->slug;

        $png = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(1000)->errorCorrection('H')->margin(2)->generate($url);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-'.$product->slug.'.png"',
        ]);
    }

    /**
     * Map an ordered list of media ids to sync payload with sort_order.
     */
    private function pivotOrder(array $ids): array
    {
        $payload = [];
        foreach (array_values($ids) as $i => $mediaId) {
            $payload[$mediaId] = ['sort_order' => $i];
        }

        return $payload;
    }
}
