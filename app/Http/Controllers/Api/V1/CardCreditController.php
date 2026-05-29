<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\UserCardCredit;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CardCreditController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $credits = $this->cardCreditsForUser(request()->user()->id);

        return $this->success([
            'available' => $credits->available(),
            'used' => $credits->used,
            'purchased' => $credits->purchased,
            'card_price' => $this->cardPrice(),
            'purchase_limits' => [
                'min_quantity' => 1,
                'max_quantity' => $this->maxPurchaseQuantity(),
            ],
        ], 'Créditos de tarjetas obtenidos correctamente.');
    }

    private function cardCreditsForUser(int $userId): UserCardCredit
    {
        return UserCardCredit::firstOrCreate(
            ['user_id' => $userId],
            [
                'purchased' => Card::where('user_id', $userId)->count(),
                'used' => Card::where('user_id', $userId)->count(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPrice(): array
    {
        return [
            'amount' => (int) env('CARD_PURCHASE_AMOUNT_CENTS', 64900),
            'currency' => strtolower((string) env('CARD_PURCHASE_CURRENCY', 'mxn')),
        ];
    }

    private function maxPurchaseQuantity(): int
    {
        return max(1, (int) env('CARD_PURCHASE_MAX_QUANTITY', 100));
    }
}
