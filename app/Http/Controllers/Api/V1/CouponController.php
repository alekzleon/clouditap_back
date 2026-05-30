<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CardOrderPricingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    use ApiResponse;

    public function validateForCardOrder(Request $request, CardOrderPricingService $pricing): JsonResponse
    {
        $validator = validator($request->all(), [
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], [
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima es :min.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $quote = $pricing->quote(
                $request->user(),
                (int) ($validator->validated()['quantity'] ?? 1),
                $validator->validated()['coupon_code'] ?? null,
            );
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage() ?: 'El cupón no es válido.', [
                'errors' => $exception->errors(),
            ], 422);
        }

        return $this->success([
            'valid' => true,
            'quote' => $this->quotePayload($quote),
        ], 'Cupón validado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function quotePayload(array $quote): array
    {
        return [
            'quantity' => $quote['quantity'],
            'unit_amount' => $quote['unit_amount'],
            'subtotal_amount' => $quote['subtotal_amount'],
            'discount_amount' => $quote['discount_amount'],
            'total_amount' => $quote['total_amount'],
            'currency' => $quote['currency'],
            'discounts' => $quote['discounts'],
        ];
    }
}
