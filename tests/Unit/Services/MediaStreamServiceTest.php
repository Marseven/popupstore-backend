<?php

namespace Tests\Unit\Services;

use App\Models\MediaContent;
use App\Models\Role;
use App\Services\MediaStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaStreamServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaStreamService $mediaStreamService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the customer role so any UserFactory usage resolves correctly
        Role::factory()->customer()->create();

        $this->mediaStreamService = new MediaStreamService();
    }

    public function test_get_signed_url_returns_valid_url(): void
    {
        $media = MediaContent::factory()->create();

        $url = $this->mediaStreamService->getSignedUrl($media, 30);

        $this->assertNotEmpty($url);
        $this->assertStringContainsString($media->uuid, $url);
    }

    public function test_get_signed_url_has_valid_signature(): void
    {
        $media = MediaContent::factory()->create();

        $url = $this->mediaStreamService->getSignedUrl($media, 30);

        // Create a request from the URL to verify its signature
        $request = \Illuminate\Http\Request::create($url);
        $this->assertTrue(URL::hasValidSignature($request));
    }

    public function test_get_signed_url_contains_media_stream_route(): void
    {
        $media = MediaContent::factory()->create();

        $url = $this->mediaStreamService->getSignedUrl($media, 30);

        // The URL should contain the media stream route pattern
        $this->assertStringContainsString('/api/media/' . $media->uuid . '/stream', $url);
    }

    public function test_get_signed_url_with_custom_expiration(): void
    {
        $media = MediaContent::factory()->create();

        $shortUrl = $this->mediaStreamService->getSignedUrl($media, 5);
        $longUrl = $this->mediaStreamService->getSignedUrl($media, 120);

        // Both should be valid signed URLs but with different expiration parameters
        $this->assertNotEmpty($shortUrl);
        $this->assertNotEmpty($longUrl);
        $this->assertNotEquals($shortUrl, $longUrl);
    }

    public function test_stream_returns_response_for_existing_audio_file(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->audio()->create([
            'file_path' => 'media/audio/test.mp3',
        ]);

        Storage::disk('local')->put('media/audio/test.mp3', 'fake audio content');

        $response = $this->mediaStreamService->stream($media);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('inline', $response->headers->get('Content-Disposition'));
        $this->assertEquals('bytes', $response->headers->get('Accept-Ranges'));
    }

    public function test_stream_returns_response_for_existing_video_file(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->video()->create([
            'file_path' => 'media/video/test.mp4',
        ]);

        Storage::disk('local')->put('media/video/test.mp4', 'fake video content');

        $response = $this->mediaStreamService->stream($media);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('video/mp4', $response->headers->get('Content-Type'));
    }

    public function test_stream_sets_security_headers(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->audio()->create([
            'file_path' => 'media/audio/test.mp3',
        ]);

        Storage::disk('local')->put('media/audio/test.mp3', 'fake audio content');

        $response = $this->mediaStreamService->stream($media);

        $this->assertEquals('no-store, no-cache, must-revalidate', $response->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_stream_sets_content_length(): void
    {
        Storage::fake('local');

        $content = 'fake audio content for length test';
        $media = MediaContent::factory()->audio()->create([
            'file_path' => 'media/audio/test.mp3',
        ]);

        Storage::disk('local')->put('media/audio/test.mp3', $content);

        $response = $this->mediaStreamService->stream($media);

        $this->assertEquals(strlen($content), $response->headers->get('Content-Length'));
    }

    public function test_stream_increments_play_count(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->audio()->create([
            'file_path' => 'media/audio/test.mp3',
            'play_count' => 5,
        ]);

        Storage::disk('local')->put('media/audio/test.mp3', 'fake audio content');

        $this->mediaStreamService->stream($media);

        $media->refresh();
        $this->assertEquals(6, $media->play_count);
    }

    public function test_stream_aborts_for_missing_file(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->audio()->create([
            'file_path' => 'media/audio/nonexistent.mp3',
        ]);

        // The file is NOT put on the fake disk, so it does not exist

        $this->expectException(HttpException::class);

        $this->mediaStreamService->stream($media);
    }

    public function test_stream_aborts_with_404_for_missing_file(): void
    {
        Storage::fake('local');

        $media = MediaContent::factory()->video()->create([
            'file_path' => 'media/video/missing.mp4',
        ]);

        try {
            $this->mediaStreamService->stream($media);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertEquals('Fichier média introuvable', $e->getMessage());
        }
    }
}
