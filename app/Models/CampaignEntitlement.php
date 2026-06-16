<?php

namespace App\Models;

use App\Enums\EntitlementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'team_id',
        'type',
        'code',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EntitlementType::class,
            'redeemed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(CampaignTeam::class, 'team_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
