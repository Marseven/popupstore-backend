<?php

namespace Database\Factories;

use App\Models\MediaContent;
use App\Models\QrScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QrScan>
 */
class QrScanFactory extends Factory
{
    protected $model = QrScan::class;

    public function definition(): array
    {
        return [
            'media_content_id' => MediaContent::factory(),
            'user_id' => fake()->optional(0.5)->passthrough(User::factory()),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'scanned_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
