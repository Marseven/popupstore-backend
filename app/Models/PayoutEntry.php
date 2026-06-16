<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'collection_id',
        'revenue_share_id',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'status',
        'paid_at',
        'payout_reference',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'commission_amount' => 'integer',
            'net_amount' => 'integer',
            'status' => PayoutStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function revenueShare(): BelongsTo
    {
        return $this->belongsTo(RevenueShare::class);
    }
}
