<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaContent;
use App\Models\QrScan;
use App\Services\MediaStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaContentController extends Controller
{
    public function __construct(private MediaStreamService $mediaStreamService) {}

    /**
     * List active media contents with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaContent::active()
            ->with('collection');

        // Filter by type
        if ($request->filled('type')) {
            $type = $request->type;
            if (in_array($type, ['audio', 'video'])) {
                $query->where('type', $type);
            }
        }

        // Filter by collection
        if ($request->filled('collection')) {
            $query->whereHas('collection', fn($q) => $q->where('slug', $request->collection));
        }

        $perPage = min($request->get('per_page', 15), 50);
        $media = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($media);
    }

    /**
     * Get media content by UUID. Log QR scan and increment play count.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $media = MediaContent::where('uuid', $uuid)
            ->active()
            ->with('collection')
            ->firstOrFail();

        // Log QR scan
        QrScan::create([
            'media_content_id' => $media->id,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'scanned_at' => now(),
        ]);

        // Increment play count
        $media->increment('play_count');

        // Generate signed stream URL
        $streamUrl = $this->mediaStreamService->getSignedUrl($media);

        return response()->json([
            'media' => $media,
            'stream_url' => $streamUrl,
        ]);
    }

    /**
     * Stream media file using MediaStreamService.
     */
    public function stream(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        // Validate signed URL
        if (!$request->hasValidSignature()) {
            return response()->json([
                'message' => 'Lien de streaming invalide ou expiré',
            ], 403);
        }

        $media = MediaContent::where('uuid', $uuid)
            ->active()
            ->firstOrFail();

        return $this->mediaStreamService->stream($media);
    }

    /**
     * Get active video contents, paginated.
     */
    public function videos(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 50);

        $videos = MediaContent::active()
            ->video()
            ->with('collection')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($videos);
    }

    /**
     * Get active audio contents, paginated.
     */
    public function audios(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 50);

        $audios = MediaContent::active()
            ->audio()
            ->with('collection')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($audios);
    }
}
