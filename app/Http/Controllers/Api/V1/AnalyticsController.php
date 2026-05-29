<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Card;
use App\Models\LinkPage;
use App\Services\TransactionalEmailService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function storePublicEvent(Request $request, LinkPage $linkPage): JsonResponse
    {
        return $this->storePublicEventForLinkPage($request, $linkPage);
    }

    public function storePublicCardEvent(Request $request, Card $card): JsonResponse
    {
        $linkPage = $card->linkPage()->first();

        if (! $linkPage) {
            return $this->error('Esta tarjeta no tiene una página pública asignada.', null, 404);
        }

        return $this->storePublicEventForLinkPage($request, $linkPage);
    }

    private function storePublicEventForLinkPage(Request $request, LinkPage $linkPage): JsonResponse
    {
        $validator = validator($request->all(), [
            'event' => ['required', Rule::in(['page_view', 'click', 'conversion'])],
            'source' => ['nullable', 'string', 'max:50'],
            'target_type' => ['nullable', Rule::in(['link', 'banner', 'social', 'button', 'whatsapp', 'phone', 'email', 'custom'])],
            'target_id' => ['nullable', 'string', 'max:120'],
            'visitor_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ], [
            'event.required' => 'El tipo de evento es obligatorio.',
            'event.in' => 'El tipo de evento no es válido.',
            'target_type.in' => 'El tipo de elemento no es válido.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $event = $this->recordEvent($request, $linkPage, $validator->validated());

        app(TransactionalEmailService::class)->sendVisitMilestoneIfNeeded($event);

        return $this->success([
            'event' => $this->eventPayload($event),
        ], 'Evento registrado correctamente.', 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);
        $selectedCard = $this->selectedCardPayload($request);

        $totalViews = (clone $query)->where('event', 'page_view')->count();
        $totalClicks = (clone $query)->where('event', 'click')->count();
        $totalConversions = (clone $query)->where('event', 'conversion')->count();
        $uniqueVisitors = (clone $query)->whereNotNull('visitor_id')->distinct('visitor_id')->count('visitor_id');

        $topCardRow = (clone $query)
            ->select('card_id', DB::raw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views"), DB::raw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks"))
            ->whereNotNull('card_id')
            ->groupBy('card_id')
            ->orderByDesc('views')
            ->first();

        $topCard = null;

        if ($topCardRow) {
            $card = Card::find($topCardRow->card_id);
            $topCard = $card ? [
                'id' => $card->id,
                'name' => $card->name,
                'slug' => $card->slug,
                'views' => (int) $topCardRow->views,
                'clicks' => (int) $topCardRow->clicks,
            ] : null;
        }

        return $this->success([
            'total_views' => $totalViews,
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'unique_visitors' => $uniqueVisitors,
            'conversion_rate' => $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0,
            'selected_card' => $selectedCard,
            'top_card' => $topCard,
        ], 'Resumen de analytics obtenido correctamente.');
    }

    public function cards(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $cards = $request->user()
            ->cards()
            ->select('id', 'name', 'slug', 'color', 'status')
            ->latest()
            ->get();

        $stats = AnalyticsEvent::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('card_id')
            ->select('card_id')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw("SUM(CASE WHEN event = 'conversion' THEN 1 ELSE 0 END) as conversions")
            ->groupBy('card_id')
            ->get()
            ->keyBy('card_id');

        $payload = $cards->map(function (Card $card) use ($stats) {
            $row = $stats->get($card->id);
            $views = (int) ($row?->views ?? 0);
            $clicks = (int) ($row?->clicks ?? 0);

            return [
                'id' => $card->id,
                'name' => $card->name,
                'slug' => $card->slug,
                'color' => $card->color,
                'status' => $card->status->value,
                'views' => $views,
                'clicks' => $clicks,
                'conversions' => (int) ($row?->conversions ?? 0),
                'conversion_rate' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0,
            ];
        })->values();

        return $this->success([
            'cards' => $payload,
            'all_option' => [
                'id' => 'all',
                'name' => 'Todas las tarjetas',
            ],
        ], 'Tarjetas para filtro de analytics obtenidas correctamente.');
    }

    public function timeseries(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->selectRaw('DATE(occurred_at) as date')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw("SUM(CASE WHEN event = 'conversion' THEN 1 ELSE 0 END) as conversions")
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'views' => (int) $row->views,
                'clicks' => (int) $row->clicks,
                'conversions' => (int) $row->conversions,
            ])
            ->values();

        return $this->success($rows, 'Gráfica de analytics obtenida correctamente.');
    }

    public function timeseriesByCard(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->whereNotNull('card_id')
            ->select('card_id')
            ->selectRaw('DATE(occurred_at) as date')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->selectRaw("SUM(CASE WHEN event = 'conversion' THEN 1 ELSE 0 END) as conversions")
            ->groupBy('card_id')
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('date')
            ->get();

        $cards = $this->cardsForRows($rows->pluck('card_id'));

        $payload = $rows
            ->groupBy('card_id')
            ->map(fn ($cardRows, int $cardId) => [
                'card' => $this->cardPayload($cards->get($cardId)),
                'series' => $cardRows->map(fn ($row) => [
                    'date' => $row->date,
                    'views' => (int) $row->views,
                    'clicks' => (int) $row->clicks,
                    'conversions' => (int) $row->conversions,
                ])->values(),
            ])
            ->values();

        return $this->success($payload, 'Gráfica por tarjeta obtenida correctamente.');
    }

    public function trafficSources(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->select('source')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->groupBy('source')
            ->orderByDesc('views')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'views' => (int) $row->views,
                'clicks' => (int) $row->clicks,
            ])
            ->values();

        return $this->success($rows, 'Fuentes de tráfico obtenidas correctamente.');
    }

    public function trafficSourcesByCard(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->whereNotNull('card_id')
            ->select('card_id', 'source')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->groupBy('card_id', 'source')
            ->orderByDesc('views')
            ->get();

        $cards = $this->cardsForRows($rows->pluck('card_id'));

        $payload = $rows
            ->groupBy('card_id')
            ->map(fn ($cardRows, int $cardId) => [
                'card' => $this->cardPayload($cards->get($cardId)),
                'sources' => $cardRows->map(fn ($row) => [
                    'source' => $row->source,
                    'views' => (int) $row->views,
                    'clicks' => (int) $row->clicks,
                ])->values(),
            ])
            ->values();

        return $this->success($payload, 'Fuentes de tráfico por tarjeta obtenidas correctamente.');
    }

    public function topLinks(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->where('event', 'click')
            ->whereNotNull('target_type')
            ->whereNotNull('target_id')
            ->select('target_type', 'target_id')
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('target_type', 'target_id')
            ->orderByDesc('clicks')
            ->limit((int) $request->integer('limit', 10))
            ->get()
            ->map(function ($row) use ($request) {
                $latest = $this->baseQuery($request)
                    ->where('event', 'click')
                    ->where('target_type', $row->target_type)
                    ->where('target_id', $row->target_id)
                    ->latest('occurred_at')
                    ->first();

                return [
                    'target_type' => $row->target_type,
                    'target_id' => $row->target_id,
                    'title' => $latest?->metadata['title'] ?? $latest?->metadata['label'] ?? null,
                    'url' => $latest?->metadata['url'] ?? null,
                    'clicks' => (int) $row->clicks,
                ];
            })
            ->values();

        return $this->success($rows, 'Top links obtenido correctamente.');
    }

    public function topLinksByCard(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 10);
        $rows = $this->baseQuery($request)
            ->where('event', 'click')
            ->whereNotNull('card_id')
            ->whereNotNull('target_type')
            ->whereNotNull('target_id')
            ->select('card_id', 'target_type', 'target_id')
            ->selectRaw('COUNT(*) as clicks')
            ->groupBy('card_id', 'target_type', 'target_id')
            ->orderByDesc('clicks')
            ->get();

        $cards = $this->cardsForRows($rows->pluck('card_id'));

        $payload = $rows
            ->groupBy('card_id')
            ->map(function ($cardRows, int $cardId) use ($cards, $request, $limit) {
                $links = $cardRows
                    ->take($limit)
                    ->map(function ($row) use ($request, $cardId) {
                        $latest = $this->baseQuery($request)
                            ->where('event', 'click')
                            ->where('card_id', $cardId)
                            ->where('target_type', $row->target_type)
                            ->where('target_id', $row->target_id)
                            ->latest('occurred_at')
                            ->first();

                        return [
                            'target_type' => $row->target_type,
                            'target_id' => $row->target_id,
                            'title' => $latest?->metadata['title'] ?? $latest?->metadata['label'] ?? null,
                            'url' => $latest?->metadata['url'] ?? null,
                            'clicks' => (int) $row->clicks,
                        ];
                    })
                    ->values();

                return [
                    'card' => $this->cardPayload($cards->get($cardId)),
                    'links' => $links,
                ];
            })
            ->values();

        return $this->success($payload, 'Top links por tarjeta obtenido correctamente.');
    }

    public function topCards(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->whereNotNull('card_id')
            ->select('card_id')
            ->selectRaw("SUM(CASE WHEN event = 'page_view' THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN event = 'click' THEN 1 ELSE 0 END) as clicks")
            ->groupBy('card_id')
            ->orderByDesc('views')
            ->limit((int) $request->integer('limit', 10))
            ->get();

        $cards = Card::whereIn('id', $rows->pluck('card_id'))->get()->keyBy('id');

        $payload = $rows->map(function ($row) use ($cards) {
            $card = $cards->get($row->card_id);
            $views = (int) $row->views;
            $clicks = (int) $row->clicks;

            return [
                'card' => $card ? [
                    'id' => $card->id,
                    'name' => $card->name,
                    'slug' => $card->slug,
                    'color' => $card->color,
                ] : null,
                'views' => $views,
                'clicks' => $clicks,
                'conversion_rate' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0,
            ];
        })->values();

        return $this->success($payload, 'Top tarjetas obtenido correctamente.');
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $events = $this->baseQuery($request)
            ->with('card:id,name,slug,color')
            ->latest('occurred_at')
            ->limit((int) $request->integer('limit', 20))
            ->get()
            ->map(fn (AnalyticsEvent $event) => $this->eventPayload($event))
            ->values();

        return $this->success($events, 'Actividad reciente obtenida correctamente.');
    }

    public function recordPageView(Request $request, LinkPage $linkPage): AnalyticsEvent
    {
        $event = $this->recordEvent($request, $linkPage, [
            'event' => 'page_view',
            'source' => $request->query('source', 'direct'),
            'visitor_id' => $request->query('visitor_id'),
            'metadata' => [
                'public_path' => "/p/{$linkPage->id}",
            ],
        ]);

        app(TransactionalEmailService::class)->sendVisitMilestoneIfNeeded($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordEvent(Request $request, LinkPage $linkPage, array $data): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'user_id' => $linkPage->user_id,
            'link_page_id' => $linkPage->id,
            'card_id' => $linkPage->card_id,
            'event' => $data['event'],
            'target_type' => $data['target_type'] ?? null,
            'target_id' => isset($data['target_id']) ? (string) $data['target_id'] : null,
            'visitor_id' => $data['visitor_id'] ?? null,
            'source' => $this->normalizeSource((string) ($data['source'] ?? $request->query('source', 'direct'))),
            'device' => $this->detectDevice($request->userAgent()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'metadata' => $data['metadata'] ?? [],
            'occurred_at' => now(),
        ]);
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));

        return in_array($source, ['nfc', 'qr', 'direct', 'social', 'referral'], true) ? $source : 'direct';
    }

    private function detectDevice(?string $userAgent): string
    {
        $userAgent = strtolower($userAgent ?? '');

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'iphone') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function baseQuery(Request $request): Builder
    {
        [$from, $to] = $this->dateRange($request);
        $cardId = $this->cardFilter($request);

        return AnalyticsEvent::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($cardId, fn (Builder $query, int $cardId) => $query->where('card_id', $cardId))
            ->when($request->integer('link_page_id'), fn (Builder $query, int $linkPageId) => $query->where('link_page_id', $linkPageId));
    }

    private function cardsForRows(mixed $cardIds)
    {
        return Card::whereIn('id', collect($cardIds)->filter()->unique()->values())
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cardPayload(?Card $card): ?array
    {
        return $card ? [
            'id' => $card->id,
            'name' => $card->name,
            'slug' => $card->slug,
            'color' => $card->color,
            'status' => $card->status->value,
        ] : null;
    }

    private function cardFilter(Request $request): ?int
    {
        $cardId = $request->query('card_id');

        if ($cardId === null || $cardId === '' || $cardId === 'all') {
            return null;
        }

        return (int) $cardId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedCardPayload(Request $request): ?array
    {
        $cardId = $this->cardFilter($request);

        if (! $cardId) {
            return null;
        }

        $card = $request->user()
            ->cards()
            ->select('id', 'name', 'slug', 'color', 'status')
            ->whereKey($cardId)
            ->first();

        return $card ? [
            'id' => $card->id,
            'name' => $card->name,
            'slug' => $card->slug,
            'color' => $card->color,
            'status' => $card->status->value,
        ] : null;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(Request $request): array
    {
        $period = $request->string('period', '30d')->toString();
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            '365d' => 365,
            default => 30,
        };

        $from = $request->date('from')
            ? CarbonImmutable::parse($request->query('from'))->startOfDay()
            : now()->toImmutable()->subDays($days - 1)->startOfDay();
        $to = $request->date('to')
            ? CarbonImmutable::parse($request->query('to'))->endOfDay()
            : now()->toImmutable()->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(AnalyticsEvent $event): array
    {
        return [
            'id' => $event->id,
            'event' => $event->event,
            'source' => $event->source,
            'device' => $event->device,
            'target_type' => $event->target_type,
            'target_id' => $event->target_id,
            'visitor_id' => $event->visitor_id,
            'metadata' => $event->metadata ?? [],
            'card' => $event->card ? [
                'id' => $event->card->id,
                'name' => $event->card->name,
                'slug' => $event->card->slug,
                'color' => $event->card->color,
            ] : null,
            'occurred_at' => $event->occurred_at,
        ];
    }
}
