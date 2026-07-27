<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = Collection::withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($collections);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('collections', 'public');
        }

        $collection = Collection::create($data);

        return response()->json(['collection' => $collection], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $collection = Collection::findOrFail($id);
        $data = $this->validated($request, $id);

        if (isset($data['name']) && $data['name'] !== $collection->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $collection->id);
        }

        if ($request->hasFile('cover_image')) {
            if ($collection->cover_image) {
                Storage::disk('public')->delete($collection->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('collections', 'public');
        }

        $collection->update($data);

        return response()->json(['collection' => $collection->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $collection = Collection::findOrFail($id);

        // Detach products so they aren't orphaned pointing at a deleted collection.
        $collection->products()->update(['collection_id' => null]);
        $collection->delete(); // soft delete

        return response()->json(['message' => 'Collection supprimée']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ($id ? 'sometimes' : 'required').'|string|max:255',
            'description' => 'nullable|string',
            'color_accent' => 'nullable|string|max:20',
            'type' => 'nullable|string|in:shop,partner,campaign',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'cover_image' => 'nullable|image|max:5120',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $exists = Collection::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->count();

        return $exists > 0 ? $slug.'-'.($exists + 1) : $slug;
    }
}
