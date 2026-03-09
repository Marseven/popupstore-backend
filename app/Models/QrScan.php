<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScan extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'media_content_id',
        'user_id',
        'ip_address',
        'user_agent',
        'scanned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * The media content that the scan belongs to.
     */
    public function mediaContent(): BelongsTo
    {
        return $this->belongsTo(MediaContent::class);
    }

    /**
     * The user that performed the scan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
