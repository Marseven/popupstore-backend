<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\MediaContent;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService) {}

    /**
     * Paginated list of media contents with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaContent::with('collection');

        // Filter by type
        if ($request->filled('type')) {
            $type = $request->type;
            if (in_array($type, ['audio', 'video'])) {
                $query->where('type', $type);
            }
        }

        // Filter by collection
        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->collection_id);
        }

        // Search by title or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->get('per_page', 15), 50);
        $media = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($media);
    }

    /**
     * Create media content with file upload and QR code generation.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Store the media file
            $file = $request->file('file');
            $filePath = $file->store('media/' . $validated['type'], 'local');
            $fileSize = $file->getSize();

            // Prepare media data
            $mediaData = [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'collection_id' => $validated['collection_id'] ?? null,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'is_active' => $validated['is_active'] ?? true,
            ];

            // Handle thumbnail for video
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('media/thumbnails', 'public');
                $mediaData['thumbnail'] = $thumbnailPath;
            }

            // Ensure unique slug
            $slugCount = MediaContent::where('slug', $mediaData['slug'])->count();
            if ($slugCount > 0) {
                $mediaData['slug'] .= '-' . ($slugCount + 1);
            }

            $media = MediaContent::create($mediaData);

            // Auto-generate QR code
            $this->qrCodeService->generateForMedia($media);

            return response()->json([
                'message' => 'Contenu média créé avec succès',
                'media' => $media->fresh()->load('collection'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du contenu média',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get media content with stats.
     */
    public function show(int $id): JsonResponse
    {
        $media = MediaContent::with('collection')
            ->withCount('qrScans')
            ->findOrFail($id);

        return response()->json([
            'media' => $media,
            'stats' => [
                'scan_count' => $media->qr_scans_count,
                'play_count' => $media->play_count,
            ],
        ]);
    }

    /**
     * Update media content details.
     */
    public function update(UpdateMediaRequest $request, int $id): JsonResponse
    {
        $media = MediaContent::findOrFail($id);
        $validated = $request->validated();

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $media->title) {
            $slug = Str::slug($validated['title']);
            $slugCount = MediaContent::where('slug', $slug)->where('id', '!=', $media->id)->count();
            if ($slugCount > 0) {
                $slug .= '-' . ($slugCount + 1);
            }
            $validated['slug'] = $slug;
        }

        // Handle thumbnail update
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($media->thumbnail) {
                Storage::disk('public')->delete($media->thumbnail);
            }
            $validated['thumbnail'] = $request->file('thumbnail')->store('media/thumbnails', 'public');
        }

        $media->update(collect($validated)->except(['thumbnail_file'])->toArray());

        return response()->json([
            'message' => 'Contenu média mis à jour avec succès',
            'media' => $media->fresh()->load('collection'),
        ]);
    }

    /**
     * Delete media content (check if linked to products first).
     */
    public function destroy(int $id): JsonResponse
    {
        $media = MediaContent::findOrFail($id);

        // Check if linked to products
        $productCount = $media->products()->count();
        if ($productCount > 0) {
            return response()->json([
                'message' => 'Ce contenu média est lié à ' . $productCount . ' produit(s). Veuillez d\'abord supprimer les liens.',
                'has_products' => true,
                'product_count' => $productCount,
            ], 409);
        }

        // Delete files
        if ($media->file_path) {
            Storage::disk('local')->delete($media->file_path);
        }
        if ($media->thumbnail) {
            Storage::disk('public')->delete($media->thumbnail);
        }
        if ($media->qr_code_path) {
            Storage::disk('public')->delete($media->qr_code_path);
        }

        // Delete related QR scans
        $media->qrScans()->delete();
        $media->delete();

        return response()->json([
            'message' => 'Contenu média supprimé avec succès',
        ]);
    }

    /**
     * Download QR code image for media.
     */
    public function downloadQr(int $id)
    {
        $media = MediaContent::findOrFail($id);

        $path = $this->qrCodeService->downloadQrCode($media);

        if (!$path) {
            return response()->json([
                'message' => 'QR code introuvable',
            ], 404);
        }

        return response()->download($path, 'qr-' . $media->uuid . '.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    /**
     * Regenerate QR code for media.
     */
    public function regenerateQr(int $id): JsonResponse
    {
        $media = MediaContent::findOrFail($id);

        $path = $this->qrCodeService->regenerateQrCode($media);

        return response()->json([
            'message' => 'QR code régénéré avec succès',
            'qr_code_path' => $path,
            'media' => $media->fresh(),
        ]);
    }
}
