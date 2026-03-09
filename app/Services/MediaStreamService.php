<?php

namespace App\Services;

use App\Models\MediaContent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamService
{
    public function getSignedUrl(MediaContent $media, int $expiresInMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'media.stream',
            now()->addMinutes($expiresInMinutes),
            ['uuid' => $media->uuid]
        );
    }

    public function stream(MediaContent $media): StreamedResponse
    {
        $filePath = $media->file_path;
        $disk = Storage::disk('local');

        if (!$disk->exists($filePath)) {
            abort(404, 'Fichier média introuvable');
        }

        $fileSize = $disk->size($filePath);
        $mimeType = $media->type === 'audio' ? 'audio/mpeg' : 'video/mp4';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ];

        // Handle range requests for seeking
        $request = request();
        $start = 0;
        $end = $fileSize - 1;
        $statusCode = 200;

        if ($request->hasHeader('Range')) {
            $range = $request->header('Range');
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

                if ($start > $end || $start >= $fileSize) {
                    return response('', 416, [
                        'Content-Range' => "bytes */$fileSize",
                    ]);
                }

                $statusCode = 206;
                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            }
        }

        $headers['Content-Length'] = $end - $start + 1;

        // Increment play count
        $media->increment('play_count');

        return response()->stream(function () use ($disk, $filePath, $start, $end) {
            $stream = $disk->readStream($filePath);
            if ($start > 0) {
                fseek($stream, $start);
            }

            $remaining = $end - $start + 1;
            $bufferSize = 8192;

            while ($remaining > 0 && !feof($stream)) {
                $readSize = min($bufferSize, $remaining);
                $data = fread($stream, $readSize);
                if ($data === false) break;
                echo $data;
                $remaining -= strlen($data);
                flush();
            }

            fclose($stream);
        }, $statusCode, $headers);
    }
}
