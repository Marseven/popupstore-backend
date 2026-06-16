<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'slug',
        'team_code',
        'producer_name',
        'artist_name',
        'color_accent',
        'qr_code_path',
        'points_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points_total' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'campaign_team_id');
    }
}
