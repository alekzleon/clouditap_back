<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CardPurchasePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly CardPurchasePaymentService $paymentService) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json([
                'message' => 'Invalid Stripe webhook payload.',
            ], 400);
        }

        $object = $event->data->object;

        if ($object instanceof PaymentIntent) {
            match ($event->type) {
                'payment_intent.succeeded' => $this->paymentService->markPaymentIntentAsSucceeded($object),
                'payment_intent.canceled', 'payment_intent.payment_failed' => $this->paymentService->markPaymentIntentAsFailed($object),
                default => null,
            };
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
