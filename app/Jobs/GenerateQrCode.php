<?php

namespace App\Jobs;

use App\Models\MediaContent;
use App\Services\QrCodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateQrCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public MediaContent $media) {}

    public function handle(QrCodeService $qrCodeService): void
    {
        $qrCodeService->generateForMedia($this->media);

        Log::info('QR code generated', [
            'media_id' => $this->media->id,
            'uuid' => $this->media->uuid,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('QR code generation failed', [
            'media_id' => $this->media->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
