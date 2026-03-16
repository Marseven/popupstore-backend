<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShippingCity extends Model
{
    protected $fillable = [
        'shipping_zone_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Lookup shipping fee by city name.
     */
    public static function getShippingFee(string $cityName): ?float
    {
        $city = static::active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityName)])
            ->with('zone')
            ->first();

        if (!$city || !$city->zone || !$city->zone->is_active) {
            return null;
        }

        return (float) $city->zone->fee;
    }
}
