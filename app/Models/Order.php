<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const TYPE_CARD_PURCHASE = 'card_purchase';

    public const TYPE_CARD_PRINT = 'card_print';

    protected $fillable = [
        'user_id',
        'card_id',
        'number',
        'type',
        'status',
        'quantity',
        'subtotal_amount',
        'discount_amount',
        'coupon_id',
        'promotion_id',
        'discount_breakdown',
        'amount',
        'currency',
        'payment_provider',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'payment_status',
        'shipping_address',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'discount_breakdown' => 'array',
            'amount' => 'integer',
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

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public static function uniqueNumber(): string
    {
        do {
            $number = 'TC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (self::where('number', $number)->exists());

        return $number;
    }
}
