<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TapHeroSetting extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'media_type',
        'media_path',
        'mobile_media_path',
        'button_one_text',
        'button_one_url',
        'button_one_active',
        'button_two_text',
        'button_two_url',
        'button_two_active',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'button_one_active' => 'boolean',
            'button_two_active' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function payload(): array
    {
        return [
            ...$this->toArray(),
            'media_url' => $this->publicUrl($this->media_path),
            'mobile_media_url' => $this->publicUrl($this->mobile_media_path),
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
