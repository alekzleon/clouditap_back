<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CardOrderPricingService
{
    /**
     * @return array<string, mixed>
     */
    public function quote(User $user, int $quantity, ?string $couponCode = null): array
    {
        $unitAmount = $this->cardUnitAmount();
        $currency = $this->currency();
        $subtotal = $unitAmount * $quantity;
        $discounts = [];
        $coupon = null;
        $promotion = $this->bestPromotion($quantity, $subtotal, $currency);

        if ($promotion) {
            $amount = $this->discountAmount($promotion->discount_type, $promotion->discount_value, $subtotal);

            if ($amount > 0) {
                $discounts[] = [
                    'type' => 'promotion',
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'amount' => $amount,
                ];
            }
        }

        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = $this->validCouponForUser($user, $couponCode, $currency);
            $baseForCoupon = max(0, $subtotal - collect($discounts)->sum('amount'));
            $amount = $this->discountAmount($coupon->discount_type, $coupon->discount_value, $baseForCoupon);

            if ($amount > 0) {
                $discounts[] = [
                    'type' => 'coupon',
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'amount' => $amount,
                ];
            }
        }

        $discountAmount = min($subtotal, collect($discounts)->sum('amount'));

        return [
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'subtotal_amount' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => max(0, $subtotal - $discountAmount),
            'amount' => max(0, $subtotal - $discountAmount),
            'currency' => $currency,
            'coupon' => $coupon,
            'promotion' => $promotion,
            'discounts' => $discounts,
        ];
    }

    public function cardUnitAmount(): int
    {
        return (int) env('CARD_PURCHASE_AMOUNT_CENTS', 64900);
    }

    public function currency(): string
    {
        return strtolower((string) env('CARD_PURCHASE_CURRENCY', 'mxn'));
    }

    private function validCouponForUser(User $user, string $code, string $currency): Coupon
    {
        $coupon = Coupon::query()
            ->where('code', Str::upper(trim($code)))
            ->withCount('assignedUsers')
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'El cupón no existe.',
            ]);
        }

        if (! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->ends_at && $coupon->ends_at->isPast())) {
            throw ValidationException::withMessages([
                'coupon_code' => 'El cupón no está vigente.',
            ]);
        }

        if ($coupon->discount_type === Coupon::TYPE_FIXED && strtolower($coupon->currency) !== $currency) {
            throw ValidationException::withMessages([
                'coupon_code' => 'El cupón no aplica para esta moneda.',
            ]);
        }

        if ($coupon->assigned_users_count > 0 && ! $coupon->assignedUsers()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Este cupón no está asignado a tu usuario.',
            ]);
        }

        if ($coupon->max_uses !== null && $coupon->redemptions()->count() >= $coupon->max_uses) {
            throw ValidationException::withMessages([
                'coupon_code' => 'El cupón ya alcanzó su límite de usos.',
            ]);
        }

        if ($coupon->max_uses_per_user !== null && $coupon->redemptions()->where('user_id', $user->id)->count() >= $coupon->max_uses_per_user) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Ya usaste este cupón el máximo de veces permitido.',
            ]);
        }

        return $coupon;
    }

    private function bestPromotion(int $quantity, int $subtotal, string $currency): ?Promotion
    {
        return Promotion::query()
            ->where('type', Promotion::TYPE_BULK_CARD_DISCOUNT)
            ->where('min_quantity', '<=', $quantity)
            ->activeForNow()
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->discount_type !== Promotion::TYPE_FIXED || strtolower($promotion->currency) === $currency)
            ->sortByDesc(fn (Promotion $promotion) => $this->discountAmount($promotion->discount_type, $promotion->discount_value, $subtotal))
            ->first();
    }

    private function discountAmount(string $type, int $value, int $baseAmount): int
    {
        if ($type === Coupon::TYPE_PERCENT) {
            return min($baseAmount, (int) floor($baseAmount * min(100, $value) / 100));
        }

        return min($baseAmount, max(0, $value));
    }
}
