<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_healthy_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['status', 'message'],
                    'storage' => ['status', 'message'],
                    'cache' => ['status', 'message'],
                ],
            ])
            ->assertJsonPath('status', 'healthy');
    }

    public function test_health_checks_report_up_when_services_available(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();

        $checks = $response->json('checks');
        $this->assertEquals('up', $checks['database']['status']);
        $this->assertEquals('up', $checks['storage']['status']);
        $this->assertEquals('up', $checks['cache']['status']);
    }

    public function test_health_returns_iso8601_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();

        $timestamp = $response->json('timestamp');
        $this->assertNotNull($timestamp);
        // Validate ISO 8601 format
        $parsed = \DateTime::createFromFormat(\DateTime::ATOM, $timestamp);
        $this->assertNotFalse($parsed);
    }
}
