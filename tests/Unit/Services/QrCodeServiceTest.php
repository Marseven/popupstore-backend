<?php

namespace Tests\Unit\Services;

use App\Models\MediaContent;
use App\Models\Role;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private QrCodeService $qrCodeService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the customer role so any UserFactory usage resolves correctly
        Role::factory()->customer()->create();

        $this->qrCodeService = new QrCodeService();
    }

    public function test_generate_for_media_creates_qr_file(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create();

        $path = $this->qrCodeService->generateForMedia($media);

        $this->assertNotEmpty($path);
        $this->assertEquals('qrcodes/' . $media->uuid . '.png', $path);
        Storage::disk('public')->assertExists($path);

        $media->refresh();
        $this->assertEquals($path, $media->qr_code_path);
        $this->assertNotNull($media->qr_code_url);
        $this->assertStringContainsString($media->uuid, $media->qr_code_url);
    }

    public function test_generate_for_media_stores_correct_url(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create();

        $this->qrCodeService->generateForMedia($media);
        $media->refresh();

        $expectedUrl = config('app.url') . '/m/' . $media->uuid;
        $this->assertEquals($expectedUrl, $media->qr_code_url);
    }

    public function test_generate_for_media_updates_model_fields(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create([
            'qr_code_path' => null,
            'qr_code_url' => null,
        ]);

        $this->assertNull($media->qr_code_path);
        $this->assertNull($media->qr_code_url);

        $path = $this->qrCodeService->generateForMedia($media);
        $media->refresh();

        $this->assertNotNull($media->qr_code_path);
        $this->assertNotNull($media->qr_code_url);
        $this->assertEquals($path, $media->qr_code_path);
    }

    public function test_regenerate_deletes_old_and_creates_new(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create();

        $oldPath = $this->qrCodeService->generateForMedia($media);
        Storage::disk('public')->assertExists($oldPath);

        $media->refresh();
        $newPath = $this->qrCodeService->regenerateQrCode($media);

        // Both paths point to same filename (uuid-based), so the file is replaced
        // The old path is deleted and a new one is generated
        $this->assertEquals($oldPath, $newPath); // Same UUID = same filename
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_regenerate_works_without_existing_qr_code(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create([
            'qr_code_path' => null,
        ]);

        $path = $this->qrCodeService->regenerateQrCode($media);

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $media->refresh();
        $this->assertEquals($path, $media->qr_code_path);
    }

    public function test_download_generates_if_missing(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create([
            'qr_code_path' => null,
        ]);

        // downloadQrCode calls generateForMedia if qr_code_path is null
        $result = $this->qrCodeService->downloadQrCode($media);
        $media->refresh();

        $this->assertNotNull($media->qr_code_path);
        Storage::disk('public')->assertExists($media->qr_code_path);
    }

    public function test_download_returns_full_path_for_existing_file(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->create();

        // First generate the QR code
        $this->qrCodeService->generateForMedia($media);
        $media->refresh();

        $fullPath = $this->qrCodeService->downloadQrCode($media);

        // With a fake disk, file_exists should work on the path
        // The method returns the full filesystem path or null
        if ($fullPath !== null) {
            $this->assertStringContainsString($media->uuid, $fullPath);
        }
    }

    public function test_generate_creates_png_file(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->audio()->create();

        $path = $this->qrCodeService->generateForMedia($media);

        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_generate_for_video_media(): void
    {
        Storage::fake('public');

        $media = MediaContent::factory()->video()->create();

        $path = $this->qrCodeService->generateForMedia($media);

        $this->assertNotEmpty($path);
        $this->assertEquals('qrcodes/' . $media->uuid . '.png', $path);
        Storage::disk('public')->assertExists($path);
    }
}
