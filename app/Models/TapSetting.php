<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TapSetting extends Model
{
    protected $fillable = [
        'logo_path',
        'footer_text',
        'whatsapp',
        'email',
        'instagram_url',
        'facebook_url',
        'tiktok_url',
        'privacy_url',
        'terms_url',
        'meta_title',
        'meta_description',
    ];

    public function payload(): array
    {
        return [
            ...$this->toArray(),
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        ];
    }
}
