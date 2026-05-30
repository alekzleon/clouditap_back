<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserCardCredit;
use App\Services\CardOrderPricingService;
use App\Services\CardPurchasePaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CardOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CardPurchasePaymentService $paymentService,
        private readonly CardOrderPricingService $pricingService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $maxQuantity = $this->maxPurchaseQuantity();

        $validator = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'integer', 'min:1', "max:{$maxQuantity}"],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'payment_provider' => ['sometimes', 'string', Rule::in(['stripe'])],
            ...$this->shippingAddressRules(),
        ], [
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad mínima es :min.',
            'quantity.max' => 'La cantidad máxima es :max.',
            'payment_provider.in' => 'El proveedor de pago no es válido.',
            ...$this->shippingAddressMessages(),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $quantity = (int) ($validator->validated()['quantity'] ?? 1);
        $paymentProvider = $validator->validated()['payment_provider'] ?? 'stripe';
        $shippingAddress = $validator->validated()['shipping_address'] ?? $request->user()->shipping_address;

        try {
            $quote = $this->pricingService->quote(
                $request->user(),
                $quantity,
                $validator->validated()['coupon_code'] ?? null,
            );
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage() ?: 'El cupón no es válido.', [
                'errors' => $exception->errors(),
            ], 422);
        }

        $order = Order::create([
            'user_id' => $request->user()->id,
            'card_id' => null,
            'number' => Order::uniqueNumber(),
            'type' => Order::TYPE_CARD_PURCHASE,
            'status' => 'pending_payment',
            'quantity' => $quantity,
            'subtotal_amount' => $quote['subtotal_amount'],
            'discount_amount' => $quote['discount_amount'],
            'coupon_id' => $quote['coupon']?->id,
            'promotion_id' => $quote['promotion']?->id,
            'discount_breakdown' => $quote['discounts'],
            'amount' => $quote['total_amount'],
            'currency' => $quote['currency'],
            'payment_provider' => $quote['total_amount'] > 0 ? $paymentProvider : 'courtesy',
            'payment_status' => $quote['total_amount'] > 0 ? 'pending' : 'succeeded',
            'shipping_address' => $shippingAddress,
        ]);

        if ($order->amount === 0) {
            $result = $this->paymentService->markOrderAsPaid($order);

            return $this->success([
                'order' => $this->orderPayload($result['order']),
                'credits' => $this->creditsPayload($result['credits']),
                'payment' => null,
            ], 'Orden cubierta por descuentos. Ya puedes crear otra tarjeta.', 201);
        }

        return $this->createStripePaymentIntent($order, $quote);
    }

    /**
     * @param  array<string, mixed>  $price
     */
    private function createStripePaymentIntent(Order $order, array $quote): JsonResponse
    {
        try {
            $paymentIntent = $this->stripe()->paymentIntents->create([
                'amount' => $order->amount,
                'currency' => $order->currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->number,
                    'user_id' => (string) $order->user_id,
                    'type' => $order->type,
                    'quantity' => (string) $order->quantity,
                    'unit_amount' => (string) $quote['unit_amount'],
                    'subtotal_amount' => (string) $order->subtotal_amount,
                    'discount_amount' => (string) $order->discount_amount,
                    'total_amount' => (string) $order->amount,
                    'coupon_id' => (string) $order->coupon_id,
                    'promotion_id' => (string) $order->promotion_id,
                    'has_shipping_address' => $order->shipping_address ? 'true' : 'false',
                ],
            ], [
                'idempotency_key' => "card-order-{$order->number}",
            ]);
        } catch (ApiErrorException $exception) {
            report($exception);

            return $this->error('No se pudo iniciar el pago con Stripe.', [
                'order' => $this->orderPayload($order),
                'stripe_error' => $exception->getMessage(),
            ], 502);
        }

        $order->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);

        return $this->success([
            'order' => $this->orderPayload($order->refresh()),
            'payment' => [
                'provider' => 'stripe',
                'publishable_key' => config('services.stripe.key'),
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ],
        ], 'Orden de tarjeta creada correctamente.', 201);
    }

    public function simulatePayment(Order $order): JsonResponse
    {
        if ($order->user_id !== request()->user()->id || $order->type !== Order::TYPE_CARD_PURCHASE) {
            return $this->error('Orden no encontrada.', null, 404);
        }

        if ($order->status !== 'pending_payment') {
            return $this->error('Esta orden no está pendiente de pago.', [
                'current_status' => $order->status,
            ], 422);
        }

        $result = $this->paymentService->markOrderAsPaid($order);

        return $this->success([
            'order' => $this->orderPayload($result['order']),
            'credits' => $this->creditsPayload($result['credits']),
        ], 'Pago simulado correctamente. Ya puedes crear otra tarjeta.');
    }

    public function syncPayment(Order $order): JsonResponse
    {
        if ($order->user_id !== request()->user()->id || $order->type !== Order::TYPE_CARD_PURCHASE) {
            return $this->error('Orden no encontrada.', null, 404);
        }

        if (! $order->stripe_payment_intent_id) {
            return $this->error('Esta orden no tiene un PaymentIntent asociado.', [
                'order' => $this->orderPayload($order),
            ], 422);
        }

        try {
            $paymentIntent = $this->stripe()->paymentIntents->retrieve($order->stripe_payment_intent_id);
        } catch (ApiErrorException $exception) {
            report($exception);

            return $this->error('No se pudo consultar el estado del pago en Stripe.', [
                'stripe_error' => $exception->getMessage(),
            ], 502);
        }

        if ($paymentIntent->status === 'succeeded') {
            $order->update([
                'payment_status' => 'succeeded',
            ]);

            $result = $this->paymentService->markOrderAsPaid($order);

            return $this->success([
                'order' => $this->orderPayload($result['order']),
                'credits' => $this->creditsPayload($result['credits']),
                'payment' => $this->paymentPayload($paymentIntent),
            ], 'Pago confirmado correctamente.');
        }

        if (in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
            $order->update([
                'payment_status' => $paymentIntent->status,
            ]);

            $order = $this->paymentService->markOrderAsPaymentFailed($order);
        }

        return $this->success([
            'order' => $this->orderPayload($order->refresh()),
            'payment' => $this->paymentPayload($paymentIntent),
        ], 'Estado de pago consultado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function creditsPayload(UserCardCredit $credits): array
    {
        return [
            'available' => $credits->available(),
            'used' => $credits->used,
            'purchased' => $credits->purchased,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPrice(): array
    {
        return [
            'amount' => $this->pricingService->cardUnitAmount(),
            'currency' => $this->pricingService->currency(),
        ];
    }

    private function maxPurchaseQuantity(): int
    {
        return max(1, (int) env('CARD_PURCHASE_MAX_QUANTITY', 100));
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        $price = $this->cardPrice();

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'quantity' => $order->quantity,
            'subtotal_amount' => $order->subtotal_amount ?? $price['amount'] * $order->quantity,
            'discount_amount' => $order->discount_amount,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'payment_provider' => $order->payment_provider,
            'payment_status' => $order->payment_status,
            'unit_amount' => $price['amount'],
            'total_amount' => $order->amount,
            'coupon_id' => $order->coupon_id,
            'promotion_id' => $order->promotion_id,
            'discounts' => $order->discount_breakdown ?? [],
            'shipping_address' => $order->shipping_address,
            'line_items' => [
                [
                    'name' => 'Tarjeta TapCloudi',
                    'quantity' => $order->quantity,
                    'unit_amount' => $price['amount'],
                    'subtotal_amount' => $order->subtotal_amount ?? $price['amount'] * $order->quantity,
                    'discount_amount' => $order->discount_amount,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(mixed $paymentIntent): array
    {
        return [
            'provider' => 'stripe',
            'payment_intent_id' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'client_secret' => $paymentIntent->client_secret,
        ];
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressRules(): array
    {
        return [
            'shipping_address' => ['sometimes', 'array'],
            'shipping_address.recipient_name' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.phone' => ['required_with:shipping_address', 'string', 'max:30'],
            'shipping_address.street' => ['required_with:shipping_address', 'string', 'max:255'],
            'shipping_address.exterior_number' => ['required_with:shipping_address', 'string', 'max:30'],
            'shipping_address.interior_number' => ['nullable', 'string', 'max:30'],
            'shipping_address.neighborhood' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.city' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.state' => ['required_with:shipping_address', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required_with:shipping_address', 'string', 'max:10'],
            'shipping_address.country' => ['required_with:shipping_address', 'string', 'size:2'],
            'shipping_address.references' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shippingAddressMessages(): array
    {
        return [
            'shipping_address.recipient_name.required_with' => 'El nombre de quien recibe es obligatorio.',
            'shipping_address.phone.required_with' => 'El teléfono es obligatorio.',
            'shipping_address.street.required_with' => 'La calle es obligatoria.',
            'shipping_address.exterior_number.required_with' => 'El número exterior es obligatorio.',
            'shipping_address.neighborhood.required_with' => 'La colonia es obligatoria.',
            'shipping_address.city.required_with' => 'La ciudad es obligatoria.',
            'shipping_address.state.required_with' => 'El estado es obligatorio.',
            'shipping_address.postal_code.required_with' => 'El código postal es obligatorio.',
            'shipping_address.country.required_with' => 'El país es obligatorio.',
            'shipping_address.country.size' => 'El país debe enviarse en formato ISO de 2 letras, por ejemplo MX.',
        ];
    }
}
