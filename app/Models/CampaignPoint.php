<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'team_id',
        'order_id',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(CampaignTeam::class, 'team_id');
    }
}
