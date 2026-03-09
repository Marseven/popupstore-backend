<?php

namespace App\Services;

use App\Models\MediaContent;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generateForMedia(MediaContent $media): string
    {
        $url = config('app.url') . '/m/' . $media->uuid;

        // Generate high-resolution QR code for printing
        $qrCode = QrCode::format('png')
            ->size(1000)
            ->errorCorrection('H')
            ->margin(2)
            ->generate($url);

        $path = 'qrcodes/' . $media->uuid . '.png';
        Storage::disk('public')->put($path, $qrCode);

        $media->update([
            'qr_code_path' => $path,
            'qr_code_url' => $url,
        ]);

        return $path;
    }

    public function downloadQrCode(MediaContent $media): ?string
    {
        if (!$media->qr_code_path) {
            $this->generateForMedia($media);
        }

        $fullPath = Storage::disk('public')->path($media->qr_code_path);

        return file_exists($fullPath) ? $fullPath : null;
    }

    public function regenerateQrCode(MediaContent $media): string
    {
        // Delete old QR code
        if ($media->qr_code_path) {
            Storage::disk('public')->delete($media->qr_code_path);
        }

        return $this->generateForMedia($media);
    }
}
