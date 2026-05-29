<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardStatusLog;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\PrintJobStatusLog;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminController extends Controller
{
    use ApiResponse;

    public function dashboard(): JsonResponse
    {
        return $this->success([
            'users_count' => User::count(),
            'cards_count' => Card::count(),
            'orders_count' => Order::count(),
            'pending_print_orders_count' => PrintJob::where('status', 'paid')
                ->count(),
            'print_queue_count' => Card::whereIn('status', [CardStatus::Ordered, CardStatus::Printing])->count(),
        ], 'Resumen administrativo obtenido correctamente.');
    }

    public function users(): JsonResponse
    {
        $users = User::withCount(['cards', 'linkPages', 'orders'])
            ->with('roles:id,name')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan->value,
                'slug' => $user->slug,
                'roles' => $user->roles->pluck('name')->values(),
                'cards_count' => $user->cards_count,
                'link_pages_count' => $user->link_pages_count,
                'orders_count' => $user->orders_count,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ])
            ->values();

        return $this->success($users, 'Usuarios obtenidos correctamente.');
    }

    public function user(User $user): JsonResponse
    {
        $user->load([
            'roles:id,name',
            'cardCredit',
            'cards.linkPage',
            'linkPages.card:id,name,slug,status',
            'orders.card:id,name,slug,status',
        ]);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $user->plan->value,
            'slug' => $user->slug,
            'roles' => $user->roles->pluck('name')->values(),
            'card_credits' => $user->cardCredit ? [
                'available' => $user->cardCredit->available(),
                'used' => $user->cardCredit->used,
                'purchased' => $user->cardCredit->purchased,
            ] : null,
            'cards' => $user->cards,
            'link_pages' => $user->linkPages,
            'orders' => $user->orders,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 'Usuario obtenido correctamente.');
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['user:id,name,email', 'card:id,user_id,name,slug,status,color'])
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('type', $type))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->get()
            ->map(fn (Order $order) => $this->orderPayload($order))
            ->values();

        return $this->success($orders, 'Pedidos obtenidos correctamente.');
    }

    public function order(Order $order): JsonResponse
    {
        $order->load(['user:id,name,email,slug,plan', 'card.user:id,name,email', 'card.linkPage']);

        return $this->success([
            ...$this->orderPayload($order),
            'print_payload' => $order->card ? $this->printCardPayload($order->card) : null,
        ], 'Pedido obtenido correctamente.');
    }

    public function printQueue(): JsonResponse
    {
        return $this->printOrders();
    }

    public function printOrders(): JsonResponse
    {
        $printJobs = PrintJob::query()
            ->with(['user:id,name,email', 'card:id,user_id,name,slug,status,color,created_at,updated_at', 'order:id,number,status,quantity,paid_at,created_at'])
            ->whereHas('card', fn ($query) => $query->whereIn('status', [
                CardStatus::Ordered,
                CardStatus::Printing,
                CardStatus::Shipped,
                CardStatus::Active,
            ]))
            ->latest()
            ->get()
            ->map(fn (PrintJob $printJob) => $this->printOrderListPayload($printJob))
            ->values();

        return $this->success($printJobs, 'Impresiones obtenidas correctamente.');
    }

    public function printOrder(PrintJob $printJob): JsonResponse
    {
        if (! $printJob->card) {
            return $this->error('Pedido de impresión no encontrado.', null, 404);
        }

        $printJob->load([
            'user:id,name,email',
            'order:id,number,status,quantity,paid_at,created_at,shipping_address',
            'card.user:id,name,email',
            'card.linkPage',
            'card.printJob.order',
            'card.statusLogs.user:id,name,email',
            'statusLogs.user:id,name,email',
        ]);

        return $this->success($this->printOrderDetailPayload($printJob), 'Impresión obtenida correctamente.');
    }

    public function printOrderInfo(PrintJob $printJob): JsonResponse
    {
        if (! $printJob->card) {
            return $this->error('Pedido de impresión no encontrado.', null, 404);
        }

        $printJob->load([
            'user:id,name,email,shipping_address',
            'order:id,number,type,status,quantity,shipping_address,paid_at,created_at',
            'card:id,user_id,name,slug,status,color',
        ]);

        return $this->success($this->printOrderInfoPayload($printJob), 'Información del pedido obtenida correctamente.');
    }

    public function printOrderStatusLogs(PrintJob $printJob): JsonResponse
    {
        if (! $printJob->card) {
            return $this->error('Pedido de impresión no encontrado.', null, 404);
        }

        $logs = $printJob->statusLogs()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (PrintJobStatusLog $log) => $this->statusLogPayload($log))
            ->values();

        return $this->success($logs, 'Historial de estatus obtenido correctamente.');
    }

    public function cardStatusLogs(Card $card): JsonResponse
    {
        $logs = $card->statusLogs()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (CardStatusLog $log) => $log->payload())
            ->values();

        return $this->success($logs, 'Historial de estatus de tarjeta obtenido correctamente.');
    }

    public function updatePrintOrderStatus(Request $request, PrintJob $printJob): JsonResponse
    {
        if (! $printJob->card) {
            return $this->error('Pedido de impresión no encontrado.', null, 404);
        }

        $validator = validator($request->all(), [
            'status' => ['required', Rule::in([
                'pending_payment',
                'paid',
                'printing',
                'shipped',
                'completed',
                'cancelled',
                'failed',
            ])],
            'note' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comment' => ['nullable', 'string', 'min:3', 'max:1000'],
            'message' => ['nullable', 'string', 'min:3', 'max:1000'],
            'reason' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comentario' => ['nullable', 'string', 'min:3', 'max:1000'],
        ], [
            'status.required' => 'El estatus del pedido es obligatorio.',
            'status.in' => 'El estatus seleccionado no es válido para este pedido.',
            'note.string' => 'La nota del cambio debe ser texto.',
            'note.min' => 'La nota del cambio debe tener al menos 3 caracteres.',
            'note.max' => 'La nota del cambio no puede exceder 1000 caracteres.',
            '*.string' => 'La nota del cambio debe ser texto.',
            '*.min' => 'La nota del cambio debe tener al menos 3 caracteres.',
            '*.max' => 'La nota del cambio no puede exceder 1000 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $status = $validated['status'];
        $fromStatus = $printJob->status;
        $note = $this->requestNote($request, 'Estatus de impresión actualizado por el administrador.');

        DB::transaction(function () use ($printJob, $request, $status, $fromStatus, $note): void {
            $printJob->update([
                'status' => $status,
                'paid_at' => in_array($status, ['paid', 'printing', 'shipped', 'completed'], true)
                    ? ($printJob->paid_at ?? now())
                    : $printJob->paid_at,
            ]);

            $cardStatus = match ($status) {
                'printing' => CardStatus::Printing,
                'shipped' => CardStatus::Shipped,
                'completed' => CardStatus::Active,
                'cancelled', 'failed' => CardStatus::Inactive,
                default => null,
            };

            if ($cardStatus) {
                $fromCardStatus = $printJob->card->status;

                $printJob->card->update(['status' => $cardStatus]);

                if ($fromCardStatus !== $cardStatus) {
                    $printJob->card->statusLogs()->create([
                        'user_id' => $request->user()->id,
                        'from_status' => $fromCardStatus->value,
                        'to_status' => $cardStatus->value,
                        'note' => $note,
                    ]);
                }
            }

            $printJob->statusLogs()->create([
                'user_id' => $request->user()->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'note' => $note,
            ]);
        });

        return $this->success($this->printOrderDetailPayload($printJob->refresh()->load([
            'user:id,name,email',
            'order:id,number,status,quantity,paid_at,created_at,shipping_address',
            'card.user:id,name,email',
            'card.linkPage',
            'card.printJob.order',
            'card.statusLogs.user:id,name,email',
            'statusLogs.user:id,name,email',
        ])), 'Estatus de impresión actualizado correctamente.');
    }

    public function reopenPrintOrder(Request $request, PrintJob $printJob): JsonResponse
    {
        if (! $printJob->card) {
            return $this->error('Pedido de impresión no encontrado.', null, 404);
        }

        $validator = validator($request->all(), [
            'note' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comment' => ['nullable', 'string', 'min:3', 'max:1000'],
            'message' => ['nullable', 'string', 'min:3', 'max:1000'],
            'reason' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comentario' => ['nullable', 'string', 'min:3', 'max:1000'],
        ], [
            'note.string' => 'La nota de reapertura debe ser texto.',
            'note.min' => 'La nota de reapertura debe tener al menos 3 caracteres.',
            'note.max' => 'La nota de reapertura no puede exceder 1000 caracteres.',
            '*.string' => 'La nota de reapertura debe ser texto.',
            '*.min' => 'La nota de reapertura debe tener al menos 3 caracteres.',
            '*.max' => 'La nota de reapertura no puede exceder 1000 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $fromStatus = $printJob->status;
        $note = $this->requestNote($request, 'Tarjeta reabierta para edición por el administrador.');

        DB::transaction(function () use ($printJob, $request, $fromStatus, $note): void {
            $printJob->update([
                'status' => 'reopened',
            ]);

            $fromCardStatus = $printJob->card->status;

            $printJob->card->update([
                'status' => CardStatus::Designing,
            ]);

            if ($fromCardStatus !== CardStatus::Designing) {
                $printJob->card->statusLogs()->create([
                    'user_id' => $request->user()->id,
                    'from_status' => $fromCardStatus->value,
                    'to_status' => CardStatus::Designing->value,
                    'note' => $note,
                ]);
            }

            $printJob->statusLogs()->create([
                'user_id' => $request->user()->id,
                'from_status' => $fromStatus,
                'to_status' => 'reopened',
                'note' => $note,
            ]);
        });

        return $this->success($this->printOrderDetailPayload($printJob->refresh()->load([
            'user:id,name,email',
            'order:id,number,status,quantity,paid_at,created_at,shipping_address',
            'card.user:id,name,email',
            'card.linkPage',
            'card.printJob.order',
            'card.statusLogs.user:id,name,email',
            'statusLogs.user:id,name,email',
        ])), 'La tarjeta fue reabierta para edición correctamente.');
    }

    public function downloadPrintSide(PrintJob $printJob, string $side): JsonResponse|BinaryFileResponse
    {
        if (! $printJob->card || ! in_array($side, ['front', 'back'], true)) {
            return $this->error('Archivo de impresión no encontrado.', null, 404);
        }

        $media = $this->printSideMedia($printJob->card, $side);

        if (! $media) {
            return $this->error('No hay archivo descargable para este lado de la tarjeta.', [
                'side' => $side,
                'expected' => 'Sube front_file/back_file o un diseño frontal/trasero como media.',
            ], 422);
        }

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'png';
        $sideName = $side === 'front' ? 'frente' : 'reverso';
        $downloadName = "{$printJob->number}_{$sideName}.{$extension}";

        return response()->download($media->getPath(), $downloadName);
    }

    public function printCard(Card $card): JsonResponse
    {
        $card->load([
            'user:id,name,email',
            'linkPage',
            'orders' => fn ($query) => $query->latest(),
            'statusLogs.user:id,name,email',
        ]);

        return $this->success($this->printCardPayload($card), 'Detalle de impresión obtenido correctamente.');
    }

    public function updateCardStatus(Request $request, Card $card): JsonResponse
    {
        $validator = validator($request->all(), [
            'status' => ['required', Rule::in([
                CardStatus::Ordered->value,
                CardStatus::Printing->value,
                CardStatus::Shipped->value,
                CardStatus::Active->value,
                CardStatus::Inactive->value,
            ])],
            'note' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comment' => ['nullable', 'string', 'min:3', 'max:1000'],
            'message' => ['nullable', 'string', 'min:3', 'max:1000'],
            'reason' => ['nullable', 'string', 'min:3', 'max:1000'],
            'comentario' => ['nullable', 'string', 'min:3', 'max:1000'],
        ], [
            'status.required' => 'El estatus es obligatorio.',
            'status.in' => 'El estatus seleccionado no es válido para administración.',
            'note.string' => 'La nota del cambio debe ser texto.',
            'note.min' => 'La nota del cambio debe tener al menos 3 caracteres.',
            'note.max' => 'La nota del cambio no puede exceder 1000 caracteres.',
            '*.string' => 'La nota del cambio debe ser texto.',
            '*.min' => 'La nota del cambio debe tener al menos 3 caracteres.',
            '*.max' => 'La nota del cambio no puede exceder 1000 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $validated = $validator->validated();
        $fromStatus = $card->status;
        $toStatus = CardStatus::from($validated['status']);
        $note = $this->requestNote($request, 'Estatus de tarjeta actualizado por el administrador.');

        $card->update(['status' => $toStatus]);

        if ($fromStatus !== $toStatus) {
            $card->statusLogs()->create([
                'user_id' => $request->user()->id,
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
                'note' => $note,
            ]);
        }

        return $this->success([
            'card' => $card->refresh(),
            'status_logs' => $card->statusLogs()
                ->with('user:id,name,email')
                ->latest()
                ->get()
                ->map(fn (CardStatusLog $log) => $log->payload())
                ->values(),
        ], 'Estatus de tarjeta actualizado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestNote(Request $request, string $default): string
    {
        foreach (['note', 'comment', 'message', 'reason', 'comentario'] as $field) {
            $value = trim((string) $request->input($field, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'type' => $order->type,
            'status' => $order->status,
            'quantity' => $order->quantity,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'paid_at' => $order->paid_at,
            'user' => $order->user ? [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
            'card' => $order->card ? [
                'id' => $order->card->id,
                'name' => $order->card->name,
                'slug' => $order->card->slug,
                'status' => $order->card->status->value,
                'color' => $order->card->color,
            ] : null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printOrderListPayload(PrintJob $printJob): array
    {
        return [
            'id' => $printJob->id,
            'number' => $printJob->number,
            'status' => $printJob->status,
            'quantity' => $printJob->quantity,
            'requested_at' => $printJob->created_at,
            'updated_at' => $printJob->updated_at,
            'order' => $printJob->order ? [
                'id' => $printJob->order->id,
                'number' => $printJob->order->number,
                'status' => $printJob->order->status,
                'quantity' => $printJob->order->quantity,
                'paid_at' => $printJob->order->paid_at,
            ] : null,
            'card' => $printJob->card ? [
                'id' => $printJob->card->id,
                'name' => $printJob->card->name,
                'slug' => $printJob->card->slug,
                'status' => $printJob->card->status->value,
                'color' => $printJob->card->color,
            ] : null,
            'user' => $printJob->user ? [
                'id' => $printJob->user->id,
                'name' => $printJob->user->name,
                'email' => $printJob->user->email,
            ] : null,
            'actions' => [
                'show' => url("/api/v1/admin/prints/{$printJob->id}"),
                'status_logs' => url("/api/v1/admin/prints/{$printJob->id}/status-logs"),
                'order_info' => url("/api/v1/admin/prints/{$printJob->id}/order-info"),
                'update_status' => url("/api/v1/admin/prints/{$printJob->id}/status"),
                'reopen' => url("/api/v1/admin/prints/{$printJob->id}/reopen"),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printOrderDetailPayload(PrintJob $printJob): array
    {
        return [
            ...$this->printOrderListPayload($printJob),
            'card_print' => $printJob->card ? $this->printCardPayload($printJob->card) : null,
            'previews' => $printJob->card ? [
                'front' => $this->printSidePayload($printJob, 'front'),
                'back' => $this->printSidePayload($printJob, 'back'),
            ] : null,
            'downloads' => [
                'front' => url("/api/v1/admin/prints/{$printJob->id}/download/front"),
                'back' => url("/api/v1/admin/prints/{$printJob->id}/download/back"),
            ],
            'status_logs' => $printJob->relationLoaded('statusLogs')
                ? $printJob->statusLogs
                    ->sortByDesc('created_at')
                    ->map(fn (PrintJobStatusLog $log) => $this->statusLogPayload($log))
                    ->values()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusLogPayload(PrintJobStatusLog $log): array
    {
        return [
            'id' => $log->id,
            'print_job_id' => $log->print_job_id,
            'from_status' => $log->from_status,
            'to_status' => $log->to_status,
            'note' => $log->note,
            'created_at' => $log->created_at,
            'admin' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printCardPayload(Card $card): array
    {
        $latestPrintJob = $card->printJob;

        return [
            'id' => $card->id,
            'name' => $card->name,
            'slug' => $card->slug,
            'color' => $card->color,
            'status' => $card->status->value,
            'status_label' => $card->status->label(),
            'nfc_id' => $card->nfc_id,
            'design_data' => $card->design_data,
            'media' => $card->mediaSummary(),
            'public_link' => $card->linkPage ? [
                'id' => $card->linkPage->id,
                'public_path' => "/p/{$card->id}",
                'public_api_url' => url("/api/v1/public/cards/{$card->id}/link-page"),
            ] : null,
            'user' => $card->user ? [
                'id' => $card->user->id,
                'name' => $card->user->name,
                'email' => $card->user->email,
            ] : null,
            'print_job' => $latestPrintJob ? [
                'id' => $latestPrintJob->id,
                'number' => $latestPrintJob->number,
                'status' => $latestPrintJob->status,
                'created_at' => $latestPrintJob->created_at,
                'order' => $latestPrintJob->order ? [
                    'id' => $latestPrintJob->order->id,
                    'number' => $latestPrintJob->order->number,
                    'status' => $latestPrintJob->order->status,
                ] : null,
                'order_info_url' => url("/api/v1/admin/prints/{$latestPrintJob->id}/order-info"),
            ] : null,
            'status_logs' => $card->relationLoaded('statusLogs')
                ? $card->statusLogs
                    ->sortByDesc('created_at')
                    ->map(fn (CardStatusLog $log) => $log->payload())
                    ->values()
                : [],
            'created_at' => $card->created_at,
            'updated_at' => $card->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printOrderInfoPayload(PrintJob $printJob): array
    {
        $shippingAddress = $this->shippingAddressForPrintOrder($printJob);

        return [
            'id' => $printJob->id,
            'number' => $printJob->number,
            'status' => $printJob->status,
            'quantity' => $printJob->quantity,
            'requested_at' => $printJob->created_at,
            'paid_at' => $printJob->paid_at,
            'order' => $printJob->order ? [
                'id' => $printJob->order->id,
                'number' => $printJob->order->number,
                'type' => $printJob->order->type,
                'status' => $printJob->order->status,
                'quantity' => $printJob->order->quantity,
                'paid_at' => $printJob->order->paid_at,
                'created_at' => $printJob->order->created_at,
            ] : null,
            'shipping_address' => $shippingAddress['address'],
            'shipping_address_source' => $shippingAddress['source'],
            'customer' => $printJob->user ? [
                'id' => $printJob->user->id,
                'name' => $printJob->user->name,
                'email' => $printJob->user->email,
            ] : null,
            'card' => $printJob->card ? [
                'id' => $printJob->card->id,
                'name' => $printJob->card->name,
                'slug' => $printJob->card->slug,
                'status' => $printJob->card->status->value,
                'color' => $printJob->card->color,
            ] : null,
            'actions' => [
                'print_detail' => url("/api/v1/admin/prints/{$printJob->id}"),
                'download_front' => url("/api/v1/admin/prints/{$printJob->id}/download/front"),
                'download_back' => url("/api/v1/admin/prints/{$printJob->id}/download/back"),
            ],
        ];
    }

    /**
     * @return array{address: mixed, source: string|null}
     */
    private function shippingAddressForPrintOrder(PrintJob $printJob): array
    {
        if ($printJob->shipping_address) {
            return [
                'address' => $printJob->shipping_address,
                'source' => 'print_job',
            ];
        }

        if ($printJob->user?->shipping_address) {
            return [
                'address' => $printJob->user->shipping_address,
                'source' => 'user_profile',
            ];
        }

        $purchaseOrder = $printJob->order && $printJob->order->shipping_address
            ? $printJob->order
            : Order::query()
                ->where('user_id', $printJob->user_id)
                ->where('type', Order::TYPE_CARD_PURCHASE)
                ->where('status', 'paid')
                ->whereNotNull('shipping_address')
                ->latest('paid_at')
                ->first();

        return [
            'address' => $purchaseOrder?->shipping_address,
            'source' => $purchaseOrder ? 'latest_paid_card_purchase' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function printSidePayload(PrintJob $printJob, string $side): array
    {
        $media = $printJob->card ? $this->printSideMedia($printJob->card, $side) : null;

        return [
            'side' => $side,
            'label' => $side === 'front' ? 'Frente' : 'Reverso',
            'preview_url' => $media?->getFullUrl(),
            'download_url' => url("/api/v1/admin/prints/{$printJob->id}/download/{$side}"),
            'download_filename' => $media
                ? "{$printJob->number}_".($side === 'front' ? 'frente' : 'reverso').'.'.(pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'png')
                : null,
            'has_file' => $media !== null,
            'media' => $media ? $printJob->card->mediaPayload($media) : null,
            'render_from_design_data' => $media === null,
        ];
    }

    private function printSideMedia(Card $card, string $side): ?Media
    {
        $needle = $side === 'front' ? 'front' : 'back';

        $printFile = $card->getMedia(Card::MEDIA_PRINT_FILES)
            ->sortByDesc('id')
            ->first(fn (Media $media) => $media->getCustomProperty('side') === $side);

        if ($printFile) {
            return $printFile;
        }

        $printFile = $card->getMedia(Card::MEDIA_PRINT_FILES)
            ->sortByDesc('id')
            ->first(fn (Media $media) => Str::contains(Str::lower($media->name.' '.$media->file_name), $needle));

        if ($printFile) {
            return $printFile;
        }

        return $side === 'front'
            ? $card->getFirstMedia(Card::MEDIA_FRONT_DESIGN)
            : $card->getFirstMedia(Card::MEDIA_BACK_DESIGN);
    }
}
