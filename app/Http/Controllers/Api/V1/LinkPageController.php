<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLinkPageRequest;
use App\Http\Requests\Api\V1\UpdateLinkPageRequest;
use App\Http\Requests\Api\V1\UploadLinkPageAssetRequest;
use App\Models\Card;
use App\Models\LinkPage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinkPageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $linkPages = request()->user()
            ->linkPages()
            ->with('card:id,name,slug')
            ->latest()
            ->get()
            ->map(fn (LinkPage $linkPage) => $linkPage->summaryPayload())
            ->values();

        return $this->success($linkPages, 'Diseños obtenidos correctamente.');
    }

    public function store(StoreLinkPageRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! $this->userOwnsCard($user, $validated['card_id'] ?? null)) {
            return $this->error('La tarjeta seleccionada no pertenece a tu cuenta.', null, 403);
        }

        $linkPage = $user->linkPages()->create($this->payloadFromValidated($validated, user: $user));
        $this->markAssignedCardAsDesigning($linkPage);

        return $this->success($linkPage->load('card:id,name,slug')->payload(), 'Diseño creado correctamente.', 201);
    }

    public function show(LinkPage $linkPage): JsonResponse
    {
        if (! $this->userOwnsLinkPage(request()->user(), $linkPage)) {
            return $this->error('No tienes permiso para ver este diseño.', null, 403);
        }

        return $this->success($linkPage->load('card:id,name,slug')->payload(), 'Diseño obtenido correctamente.');
    }

    public function update(UpdateLinkPageRequest $request, LinkPage $linkPage): JsonResponse
    {
        if (! $this->userOwnsLinkPage($request->user(), $linkPage)) {
            return $this->error('No tienes permiso para actualizar este diseño.', null, 403);
        }

        $validated = $request->validated();

        if (! $this->userOwnsCard($request->user(), $validated['card_id'] ?? null)) {
            return $this->error('La tarjeta seleccionada no pertenece a tu cuenta.', null, 403);
        }

        $linkPage->update($this->payloadFromValidated($validated, $linkPage, $request->user()));
        $this->markAssignedCardAsDesigning($linkPage);

        return $this->success($linkPage->refresh()->load('card:id,name,slug')->payload(), 'Diseño guardado correctamente.');
    }

    public function destroy(LinkPage $linkPage): JsonResponse
    {
        if (! $this->userOwnsLinkPage(request()->user(), $linkPage)) {
            return $this->error('No tienes permiso para eliminar este diseño.', null, 403);
        }

        return $this->destroyDesign($linkPage);
    }

    public function destroyByCard(Card $card): JsonResponse
    {
        if (! $this->userOwnsCardModel(request()->user(), $card)) {
            return $this->error('No tienes permiso para eliminar el diseño de esta tarjeta.', null, 403);
        }

        $linkPage = $card->linkPage()->first();

        if (! $linkPage) {
            return $this->error('Esta tarjeta no tiene un diseño virtual asignado.', null, 404);
        }

        return $this->destroyDesign($linkPage);
    }

    public function uploadAsset(UploadLinkPageAssetRequest $request, LinkPage $linkPage): JsonResponse
    {
        if (! $this->userOwnsLinkPage($request->user(), $linkPage)) {
            return $this->error('No tienes permiso para subir archivos a este diseño.', null, 403);
        }

        $validated = $request->validated();
        $collection = $this->collectionFromType($validated['type']);
        $fileAdder = $linkPage->addMediaFromRequest('file');

        if ($validated['type'] === 'banner') {
            $fileAdder->withCustomProperties([
                'banner_id' => (string) $validated['banner_id'],
            ]);
        }

        $media = $fileAdder->toMediaCollection($collection);

        return $this->success([
            'media' => $linkPage->mediaPayload($media),
        ], 'Archivo subido correctamente.', 201);
    }

    public function destroyLink(LinkPage $linkPage, string $link): JsonResponse
    {
        if (! $this->userOwnsLinkPage(request()->user(), $linkPage)) {
            return $this->error('No tienes permiso para actualizar este diseño.', null, 403);
        }

        $links = collect($linkPage->links ?? [])
            ->reject(fn (array $item) => (string) ($item['id'] ?? '') === $link)
            ->values()
            ->all();

        $linkPage->update(['links' => $links]);

        return $this->success(null, 'Link eliminado correctamente.');
    }

    public function destroyBanner(LinkPage $linkPage, string $banner): JsonResponse
    {
        if (! $this->userOwnsLinkPage(request()->user(), $linkPage)) {
            return $this->error('No tienes permiso para actualizar este diseño.', null, 403);
        }

        $banners = collect($linkPage->banners ?? [])
            ->reject(fn (array $item) => (string) ($item['id'] ?? '') === $banner)
            ->values()
            ->all();

        $linkPage->getMedia(LinkPage::MEDIA_BANNER_IMAGES)
            ->filter(fn ($media) => (string) $media->getCustomProperty('banner_id') === $banner)
            ->each(fn ($media) => $media->delete());

        $linkPage->update(['banners' => $banners]);

        return $this->success(null, 'Banner eliminado correctamente.');
    }

    public function publicShow(Request $request, LinkPage $linkPage): JsonResponse
    {
        app(AnalyticsController::class)->recordPageView($request, $linkPage);

        return $this->success($linkPage->payload(activeOnly: true), 'Página pública obtenida correctamente.');
    }

    public function publicShowByCard(Request $request, Card $card): JsonResponse
    {
        $linkPage = $card->linkPage()->first();

        if (! $linkPage) {
            return $this->error('Esta tarjeta no tiene una página pública asignada.', null, 404);
        }

        return $this->publicShow($request, $linkPage);
    }

    public function legacyShow(): JsonResponse
    {
        $linkPage = $this->firstOrCreateForUser(request()->user());

        return $this->success($linkPage->load('card:id,name,slug')->payload(), 'Link page obtenida correctamente.');
    }

    public function legacyUpdate(UpdateLinkPageRequest $request): JsonResponse
    {
        $linkPage = $this->firstOrCreateForUser($request->user());

        return $this->update($request, $linkPage);
    }

    public function legacyUploadAsset(UploadLinkPageAssetRequest $request): JsonResponse
    {
        $linkPage = $this->firstOrCreateForUser($request->user());

        return $this->uploadAsset($request, $linkPage);
    }

    private function firstOrCreateForUser(User $user): LinkPage
    {
        return $user->linkPages()->firstOrCreate([], [
            ...$this->defaultPayload($user, $this->uniqueSlug($user->slug ?: $user->name)),
            'card_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadFromValidated(array $validated, ?LinkPage $linkPage = null, ?User $user = null): array
    {
        $payload = [
            'card_id' => $validated['card_id'] ?? null,
            'profile' => $this->profileFromValidated($validated['profile'] ?? []),
            'theme' => [
                ...$this->defaultTheme(),
                ...($validated['theme'] ?? []),
            ],
            'socials' => $validated['socials'] ?? [],
            'links' => $validated['links'] ?? [],
            'banners' => $validated['banners'] ?? [],
            'status' => $validated['status'] ?? 'active',
        ];

        if (! $linkPage) {
            $payload['slug'] = $this->uniqueSlug($this->internalSlugBase($validated, $user));
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(User $user, string $slug): array
    {
        return [
            'slug' => $slug,
            'profile' => [
                ...$this->defaultProfile(),
                'name' => $user->name,
            ],
            'theme' => $this->defaultTheme(),
            'socials' => [],
            'links' => [],
            'banners' => [],
            'status' => 'active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultProfile(): array
    {
        return [
            'name' => '',
            'subtitle' => '',
            'cover_position' => 'center',
            'footer' => 'TapCloudi',
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function profileFromValidated(array $profile): array
    {
        $profile = [
            ...$this->defaultProfile(),
            ...$profile,
        ];

        return [
            'name' => $profile['name'] ?? '',
            'subtitle' => $profile['subtitle'] ?? '',
            'cover_position' => $profile['cover_position'] ?? 'center',
            'footer' => $profile['footer'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTheme(): array
    {
        return [
            'background' => '#0f1115',
            'card' => '#171a21',
            'card_soft' => '#1e2430',
            'text' => '#f6f7fb',
            'muted' => '#b9c0cf',
            'primary' => '#ff2669',
            'radius' => 22,
            'social_style' => 'lineal',
        ];
    }

    private function uniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value) ?: 'link-page';
        $slug = $baseSlug;
        $suffix = 2;

        while (LinkPage::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function internalSlugBase(array $validated, ?User $user): string
    {
        $profileName = $validated['profile']['name'] ?? null;

        if (is_string($profileName) && trim($profileName) !== '') {
            return $profileName;
        }

        if (! empty($validated['card_id'])) {
            return "card-{$validated['card_id']}";
        }

        return $user?->name ?: 'link-page';
    }

    private function collectionFromType(string $type): string
    {
        return match ($type) {
            'cover' => LinkPage::MEDIA_COVER,
            'avatar' => LinkPage::MEDIA_AVATAR,
            'banner' => LinkPage::MEDIA_BANNER_IMAGES,
        };
    }

    private function userOwnsLinkPage(User $user, LinkPage $linkPage): bool
    {
        return $linkPage->user_id === $user->id;
    }

    private function userOwnsCard(User $user, mixed $cardId): bool
    {
        if ($cardId === null || $cardId === '') {
            return true;
        }

        return $user->cards()->whereKey($cardId)->exists();
    }

    private function userOwnsCardModel(User $user, Card $card): bool
    {
        return $card->user_id === $user->id;
    }

    private function markAssignedCardAsDesigning(LinkPage $linkPage): void
    {
        $card = $linkPage->card()->first();

        if ($card instanceof Card && $card->status === CardStatus::Draft) {
            $card->update(['status' => CardStatus::Designing]);
        }
    }

    private function destroyDesign(LinkPage $linkPage): JsonResponse
    {
        $result = DB::transaction(function () use ($linkPage): array {
            $lockedLinkPage = LinkPage::whereKey($linkPage->id)->lockForUpdate()->firstOrFail();
            $releasedCard = $lockedLinkPage->card()->lockForUpdate()->first();
            $deletedDesign = [
                'id' => $lockedLinkPage->id,
                'card_id' => $lockedLinkPage->card_id,
                'profile' => $lockedLinkPage->payload()['profile'],
            ];

            $lockedLinkPage->delete();

            if ($releasedCard instanceof Card) {
                $this->markCardAsDraftAfterDesignDeletion($releasedCard);
                $releasedCard = $releasedCard->refresh();
            }

            return [
                'deleted_design' => $deletedDesign,
                'released_card' => $releasedCard ? [
                    'id' => $releasedCard->id,
                    'name' => $releasedCard->name,
                    'slug' => $releasedCard->slug,
                    'status' => $releasedCard->status->value,
                    'link_page_id' => null,
                    'public_path' => null,
                ] : null,
            ];
        });

        return $this->success($result, 'Diseño eliminado correctamente. La tarjeta quedó desasignada.');
    }

    private function markCardAsDraftAfterDesignDeletion(Card $card): void
    {
        if ($card->status !== CardStatus::Designing || ! empty($card->design_data)) {
            return;
        }

        $card->update([
            'status' => CardStatus::Draft,
        ]);

        $card->statusLogs()->create([
            'user_id' => request()->user()->id,
            'from_status' => CardStatus::Designing->value,
            'to_status' => CardStatus::Draft->value,
            'note' => 'El cliente eliminó el diseño virtual asignado a la tarjeta.',
        ]);
    }
}
