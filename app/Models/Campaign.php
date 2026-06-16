<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'starts_at',
        'ends_at',
        'status',
        'sales_goal',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => CampaignStatus::class,
            'sales_goal' => 'integer',
            'settings' => 'array',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(CampaignTeam::class);
    }

    /**
     * Whether points may be awarded right now (active + inside the window).
     */
    public function isAcceptingPoints(?\DateTimeInterface $at = null): bool
    {
        if ($this->status !== CampaignStatus::Active) {
            return false;
        }

        $at = $at ?: now();

        if ($this->starts_at && $at < $this->starts_at) {
            return false;
        }

        if ($this->ends_at && $at > $this->ends_at) {
            return false;
        }

        return true;
    }
}
