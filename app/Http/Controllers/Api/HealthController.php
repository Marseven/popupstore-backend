<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
        ];

        $allUp = collect($checks)->every(fn (array $check) => $check['status'] === 'up');

        return response()->json([
            'status' => $allUp ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allUp ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'up',
                'message' => 'Database connection established',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $filename = 'health_check_' . uniqid() . '.tmp';

            Storage::disk('local')->put($filename, 'health_check');
            Storage::disk('local')->delete($filename);

            return [
                'status' => 'up',
                'message' => 'Storage is writable',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 10);
            $value = Cache::get('health_check');

            if ($value !== true) {
                return [
                    'status' => 'down',
                    'message' => 'Cache read returned unexpected value',
                ];
            }

            return [
                'status' => 'up',
                'message' => 'Cache is operational',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'message' => 'Cache check failed: ' . $e->getMessage(),
            ];
        }
    }
}
