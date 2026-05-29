<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCardRequest;
use App\Http\Requests\Api\V1\UpdateCardNameRequest;
use App\Http\Requests\Api\V1\UpdateCardRequest;
use App\Models\Card;
use App\Models\CardStatusLog;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\UserCardCredit;
use App\Services\TransactionalEmailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Card::class);

        $cards = request()->user()
            ->cards()
            ->latest()
            ->get();

        return $this->success([
            'cards' => $cards,
        ], 'Tarjetas obtenidas correctamente.');
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $requestedStatus = isset($validated['status']) ? CardStatus::from($validated['status']) : null;

        if ($requestedStatus && ! in_array($requestedStatus, [CardStatus::Draft, CardStatus::Designing], true)) {
            return $this->error('Una tarjeta nueva solo puede crearse como borrador o en diseño.', null, 422);
        }

        $result = DB::transaction(function () use ($user, $validated): array {
            $credits = $this->cardCreditsForUser($user->id);

            if ($credits->available() <= 0) {
                return [
                    'error' => true,
                    'response' => $this->error('Necesitas comprar otra tarjeta para continuar.', [
                        'code' => 'CARD_PURCHASE_REQUIRED',
                        'available' => 0,
                        'card_price' => $this->cardPrice(),
                    ], 402),
                ];
            }

            $card = $user->cards()->create([
                ...$validated,
                'slug' => $this->uniqueCardSlug($validated['name']),
                'status' => $validated['status'] ?? CardStatus::Draft,
            ]);

            $card->statusLogs()->create([
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => $card->status->value,
                'note' => 'Tarjeta creada por el cliente.',
            ]);

            $credits->increment('used');

            return [
                'error' => false,
                'card' => $card,
                'credits' => $credits->refresh(),
            ];
        });

        if ($result['error']) {
            return $result['response'];
        }

        return $this->success([
            'card' => $result['card'],
            'credits' => $this->creditsPayload($result['credits']),
        ], 'Tarjeta creada correctamente.', 201);
    }

    public function show(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        return $this->success([
            'card' => $card,
        ], 'Tarjeta obtenida correctamente.');
    }

    public function statusLogs(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        $logs = $card->statusLogs()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (CardStatusLog $log) => $log->payload())
            ->values();

        return $this->success($logs, 'Historial de estatus de tarjeta obtenido correctamente.');
    }

    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        if (! $card->isEditable()) {
            return $this->error('Esta tarjeta ya fue enviada a impresión y no puede editarse.', null, 403);
        }

        $validated = $request->validated();

        $requestedStatus = isset($validated['status']) ? CardStatus::from($validated['status']) : null;

        if ($requestedStatus && ! in_array($requestedStatus, [CardStatus::Draft, CardStatus::Designing], true)) {
            return $this->error('Para mandar la tarjeta a impresión usa el botón Imprimir.', null, 422);
        }

        if (array_key_exists('name', $validated) && $validated['name'] !== $card->name) {
            $validated['slug'] = $this->uniqueCardSlug($validated['name'], $card->id);
        }

        $fromStatus = $card->status;

        $card->update($validated);

        if (isset($validated['status']) && $card->status !== $fromStatus) {
            $card->statusLogs()->create([
                'user_id' => $request->user()->id,
                'from_status' => $fromStatus->value,
                'to_status' => $card->status->value,
                'note' => 'Estatus actualizado por el cliente.',
            ]);
        }

        return $this->success([
            'card' => $card->refresh(),
        ], 'Tarjeta actualizada correctamente.');
    }

    public function updateName(UpdateCardNameRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        if (! $card->isEditable()) {
            return $this->error('Esta tarjeta ya fue enviada a impresión y no puede editarse.', null, 403);
        }

        $validated = $request->validated();

        $card->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueCardSlug($validated['name'], $card->id),
        ]);

        return $this->success([
            'card' => $card->refresh(),
        ], 'Nombre de tarjeta actualizado correctamente.');
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->authorize('delete', $card);

        DB::transaction(function () use ($card): void {
            $credits = $this->cardCreditsForUser($card->user_id);

            $card->delete();

            if ($credits->used > 0) {
                $credits->decrement('used');
            }
        });

        return $this->success(null, 'Tarjeta eliminada correctamente.');
    }

    public function print(Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $result = DB::transaction(function () use ($card): array {
            $lockedCard = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! $lockedCard->isReadyToRequestPrint()) {
                return [
                    'error' => true,
                    'response' => $this->error('La tarjeta debe estar en estatus En diseño para solicitar impresión.', [
                        'current_status' => $lockedCard->status->value,
                        'required_status' => CardStatus::Designing->value,
                    ], 422),
                ];
            }

            $linkPage = $lockedCard->linkPage()->first();

            if (! $linkPage) {
                return [
                    'error' => true,
                    'response' => $this->error('Asigna un diseño/link page a esta tarjeta antes de solicitar impresión.', null, 422),
                ];
            }

            $reopenedPrintJob = $lockedCard->printJob()
                ->where('status', 'reopened')
                ->lockForUpdate()
                ->first();

            $fromCardStatus = $lockedCard->status;

            $lockedCard->update([
                'status' => CardStatus::Ordered,
            ]);

            if ($reopenedPrintJob) {
                $reopenedPrintJob->update([
                    'status' => 'paid',
                    'paid_at' => $reopenedPrintJob->paid_at ?? now(),
                ]);

                $lockedCard->statusLogs()->create([
                    'user_id' => request()->user()->id,
                    'from_status' => $fromCardStatus->value,
                    'to_status' => CardStatus::Ordered->value,
                    'note' => 'La tarjeta fue enviada nuevamente a impresión sin generar un nuevo pago.',
                ]);

                return [
                    'error' => false,
                    'card' => $lockedCard->refresh(),
                    'link_page' => $linkPage->refresh()->payload(),
                'print_job' => $reopenedPrintJob->refresh(),
                'order' => $reopenedPrintJob->order()->first(),
                'reused_order' => true,
                'payment_required' => false,
                ];
            }

            $purchaseOrder = $this->purchaseOrderForPrint($lockedCard);

            $printJob = PrintJob::create([
                'user_id' => $lockedCard->user_id,
                'card_id' => $lockedCard->id,
                'order_id' => $purchaseOrder?->id,
                'number' => PrintJob::uniqueNumber(),
                'status' => 'paid',
                'quantity' => 1,
                'shipping_address' => $lockedCard->user?->shipping_address,
                'paid_at' => now(),
            ]);

            $lockedCard->statusLogs()->create([
                'user_id' => request()->user()->id,
                'from_status' => $fromCardStatus->value,
                'to_status' => CardStatus::Ordered->value,
                'note' => 'Solicitud de impresión creada por el cliente.',
            ]);

            return [
                'error' => false,
                'card' => $lockedCard->refresh(),
                'link_page' => $linkPage->refresh()->payload(),
                'print_job' => $printJob,
                'order' => $purchaseOrder,
                'reused_order' => false,
                'payment_required' => false,
            ];
        });

        if ($result['error']) {
            return $result['response'];
        }

        unset($result['error']);

        app(TransactionalEmailService::class)->sendCardSentToPrint($result['card'], $result['print_job']);

        if ($result['reused_order']) {
            return $this->success($result, 'La tarjeta fue enviada nuevamente a impresión sin generar un nuevo pago.');
        }

        return $this->success($result, 'Solicitud de impresión creada correctamente.', 201);
    }

    private function purchaseOrderForPrint(Card $card): ?Order
    {
        return Order::query()
            ->where('user_id', $card->user_id)
            ->where('type', Order::TYPE_CARD_PURCHASE)
            ->where('status', 'paid')
            ->whereRaw('quantity > (
                select count(*)
                from print_jobs
                where print_jobs.order_id = orders.id
            )')
            ->oldest('paid_at')
            ->first()
            ?? Order::query()
                ->where('user_id', $card->user_id)
                ->where('type', Order::TYPE_CARD_PURCHASE)
                ->where('status', 'paid')
                ->latest('paid_at')
                ->first();
    }

    private function uniqueCardSlug(string $name, ?int $ignoreCardId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'tarjeta';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Card::where('slug', $slug)
                ->when($ignoreCardId, fn ($query) => $query->whereKeyNot($ignoreCardId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
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
            'amount' => (int) env('CARD_PURCHASE_AMOUNT_CENTS', 64900),
            'currency' => strtolower((string) env('CARD_PURCHASE_CURRENCY', 'mxn')),
        ];
    }
}
