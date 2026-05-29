<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Card extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const MEDIA_LOGOS = 'card_logos';

    public const MEDIA_FRONT_DESIGN = 'card_front_design';

    public const MEDIA_BACK_DESIGN = 'card_back_design';

    public const MEDIA_PRINT_FILES = 'card_print_files';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'status',
        'nfc_id',
        'design_data',
        'design_file',
        'qr_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'design_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function linkPage(): HasOne
    {
        return $this->hasOne(LinkPage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function printJob(): HasOne
    {
        return $this->hasOne(PrintJob::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CardStatusLog::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [CardStatus::Draft, CardStatus::Designing], true);
    }

    public function isReadyToRequestPrint(): bool
    {
        return $this->status === CardStatus::Designing;
    }

    public function isPrintLocked(): bool
    {
        return in_array($this->status, [
            CardStatus::Ordered,
            CardStatus::Printing,
            CardStatus::Shipped,
            CardStatus::Active,
            CardStatus::Inactive,
        ], true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_LOGOS)->useDisk('public');

        $this->addMediaCollection(self::MEDIA_FRONT_DESIGN)->useDisk('public')->singleFile();
        $this->addMediaCollection(self::MEDIA_BACK_DESIGN)->useDisk('public')->singleFile();
        $this->addMediaCollection(self::MEDIA_PRINT_FILES)->useDisk('public');
    }

    /**
     * @return array<string, mixed>
     */
    public function mediaSummary(): array
    {
        return [
            'logos' => $this->getMedia(self::MEDIA_LOGOS)->map(fn (Media $media) => $this->mediaPayload($media))->values(),
            'front_design' => $this->getFirstMedia(self::MEDIA_FRONT_DESIGN)
                ? $this->mediaPayload($this->getFirstMedia(self::MEDIA_FRONT_DESIGN))
                : null,
            'back_design' => $this->getFirstMedia(self::MEDIA_BACK_DESIGN)
                ? $this->mediaPayload($this->getFirstMedia(self::MEDIA_BACK_DESIGN))
                : null,
            'print_files' => $this->getMedia(self::MEDIA_PRINT_FILES)->map(fn (Media $media) => $this->mediaPayload($media))->values(),
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
        ];
    }
}
