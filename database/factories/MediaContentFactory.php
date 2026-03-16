<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\MediaContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaContent>
 */
class MediaContentFactory extends Factory
{
    protected $model = MediaContent::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $type = fake()->randomElement(['audio', 'video']);

        return [
            'collection_id' => Collection::factory(),
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'type' => $type,
            'file_path' => "media/{$type}/" . fake()->uuid() . ($type === 'audio' ? '.mp3' : '.mp4'),
            'file_size' => fake()->numberBetween(1000000, 50000000),
            'duration' => fake()->numberBetween(60, 600),
            'thumbnail' => null,
            'qr_code_path' => null,
            'qr_code_url' => null,
            'play_count' => fake()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }

    public function audio(): static
    {
        return $this->state(fn () => [
            'type' => 'audio',
            'file_path' => 'media/audio/' . fake()->uuid() . '.mp3',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'type' => 'video',
            'file_path' => 'media/video/' . fake()->uuid() . '.mp4',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
