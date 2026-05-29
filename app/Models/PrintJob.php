<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_id',
        'order_id',
        'number',
        'status',
        'quantity',
        'shipping_address',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PrintJobStatusLog::class);
    }

    public static function uniqueNumber(): string
    {
        do {
            $number = 'PR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('number', $number)->exists());

        return $number;
    }
}
