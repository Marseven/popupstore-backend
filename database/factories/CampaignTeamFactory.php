<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignTeam>
 */
class CampaignTeamFactory extends Factory
{
    protected $model = CampaignTeam::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'campaign_id' => Campaign::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'team_code' => strtoupper(Str::random(8)),
            'producer_name' => fake()->name(),
            'artist_name' => fake()->name(),
            'color_accent' => fake()->hexColor(),
            'points_total' => 0,
            'sort_order' => 0,
        ];
    }
}
