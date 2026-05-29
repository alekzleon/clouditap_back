<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LinkPage extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const MEDIA_COVER = 'link_page_cover';

    public const MEDIA_AVATAR = 'link_page_avatar';

    public const MEDIA_BANNER_IMAGES = 'link_page_banner_images';

    protected $fillable = [
        'user_id',
        'card_id',
        'slug',
        'profile',
        'theme',
        'socials',
        'links',
        'banners',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'theme' => 'array',
            'socials' => 'array',
            'links' => 'array',
            'banners' => 'array',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COVER)->useDisk('public')->singleFile();
        $this->addMediaCollection(self::MEDIA_AVATAR)->useDisk('public')->singleFile();
        $this->addMediaCollection(self::MEDIA_BANNER_IMAGES)->useDisk('public');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(bool $activeOnly = false): array
    {
        $banners = $this->filteredItems($this->banners ?? [], $activeOnly);
        $payload = [
            'id' => $this->id,
            'profile' => $this->profilePayload(),
            'theme' => $this->theme,
            'socials' => $this->filteredItems($this->socials ?? [], $activeOnly),
            'links' => $this->filteredItems($this->links ?? [], $activeOnly),
            'banners' => $this->bannersWithImageUrls($banners),
            'media' => $this->mediaSummary(
                $activeOnly ? array_map(fn (array $banner) => (string) ($banner['id'] ?? ''), $banners) : null
            ),
        ];

        if (! $activeOnly) {
            $payload['public_path'] = $this->card_id ? "/p/{$this->card_id}" : null;
            $payload['card_id'] = $this->card_id;
            $payload['status'] = $this->status;
            $payload['created_at'] = $this->created_at;
            $payload['updated_at'] = $this->updated_at;
            $payload['user_id'] = $this->user_id;
            $payload['card'] = $this->card ? [
                'id' => $this->card->id,
                'name' => $this->card->name,
                'slug' => $this->card->slug,
            ] : null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryPayload(): array
    {
        return [
            'id' => $this->id,
            'public_path' => $this->card_id ? "/p/{$this->card_id}" : null,
            'card_id' => $this->card_id,
            'card' => $this->card ? [
                'id' => $this->card->id,
                'name' => $this->card->name,
                'slug' => $this->card->slug,
            ] : null,
            'profile' => $this->profilePayload(),
            'links_count' => count($this->links ?? []),
            'banners_count' => count($this->banners ?? []),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @param  list<string>|null  $bannerIds
     * @return array<string, mixed>
     */
    public function mediaSummary(?array $bannerIds = null): array
    {
        $bannerImages = $this->getMedia(self::MEDIA_BANNER_IMAGES)
            ->when($bannerIds !== null, fn ($media) => $media->filter(
                fn (Media $item) => in_array((string) $item->getCustomProperty('banner_id'), $bannerIds, true)
            ));

        return [
            'cover' => $this->getFirstMedia(self::MEDIA_COVER)
                ? $this->mediaPayload($this->getFirstMedia(self::MEDIA_COVER))
                : null,
            'avatar' => $this->getFirstMedia(self::MEDIA_AVATAR)
                ? $this->mediaPayload($this->getFirstMedia(self::MEDIA_AVATAR))
                : null,
            'banner_images' => $bannerImages->map(fn (Media $media) => $this->mediaPayload($media))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'collection' => $media->collection_name,
            'disk' => $media->disk,
            'original_url' => $media->getFullUrl(),
            'url' => $media->getUrl(),
            'custom_properties' => $media->custom_properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(): array
    {
        return [
            'name' => $this->profile['name'] ?? '',
            'subtitle' => $this->profile['subtitle'] ?? '',
            'cover_position' => $this->profile['cover_position'] ?? 'center',
            'footer' => $this->profile['footer'] ?? '',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filteredItems(array $items, bool $activeOnly): array
    {
        $filtered = $activeOnly
            ? array_filter($items, fn (array $item) => ($item['active'] ?? false) === true)
            : $items;

        usort($filtered, fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return array_values($filtered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $banners
     * @return array<int, array<string, mixed>>
     */
    private function bannersWithImageUrls(array $banners): array
    {
        $mediaByBannerId = $this->getMedia(self::MEDIA_BANNER_IMAGES)
            ->keyBy(fn (Media $media) => (string) $media->getCustomProperty('banner_id'));

        return array_map(function (array $banner) use ($mediaByBannerId): array {
            $bannerId = (string) ($banner['id'] ?? '');
            $media = $mediaByBannerId->get($bannerId);

            if ($media) {
                $banner['media_id'] = $media->id;
                $banner['image_url'] = $media->getFullUrl();
            }

            return $banner;
        }, $banners);
    }
}
