<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_id',
        'link_page_id',
        'card_id',
        'event',
        'target_type',
        'target_id',
        'visitor_id',
        'source',
        'device',
        'ip_address',
        'user_agent',
        'referer',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }
}
