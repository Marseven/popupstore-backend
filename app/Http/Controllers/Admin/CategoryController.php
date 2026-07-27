<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ProductCategory::withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = ProductCategory::create($data);
        Cache::forget('categories.active');

        return response()->json(['category' => $category], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = ProductCategory::findOrFail($id);
        $data = $this->validated($request, $id);

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);
        Cache::forget('categories.active');

        return response()->json(['category' => $category->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = ProductCategory::findOrFail($id);

        // ProductCategory has no soft-delete; keep products but drop the link.
        $category->products()->update(['category_id' => null]);
        $category->delete();
        Cache::forget('categories.active');

        return response()->json(['message' => 'Catégorie supprimée']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ($id ? 'sometimes' : 'required').'|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:product_categories,id',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'image' => 'nullable|image|max:5120',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $exists = ProductCategory::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->count();

        return $exists > 0 ? $slug.'-'.($exists + 1) : $slug;
    }
}
