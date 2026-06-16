<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private MerchantService $merchants) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->merchants->scopedProducts($request->user())
            ->with('images')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        // Ownership: the target collection MUST belong to this merchant.
        $owned = $this->merchants->ownedCollectionIds($request->user());
        abort_unless(in_array((int) $validated['collection_id'], $owned, true), 403, 'Collection non autorisée');

        $product = Collection::findOrFail($validated['collection_id'])->products()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(6),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['product' => $product], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        // findOwnedProduct → 404 for products outside the merchant's collections.
        $product = $this->merchants->findOwnedProduct($request->user(), $id);

        $product->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]));

        return response()->json(['product' => $product->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->merchants->findOwnedProduct($request->user(), $id)->delete();

        return response()->json(['message' => 'Produit supprimé']);
    }
}
