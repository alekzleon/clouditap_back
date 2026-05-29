<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TapReview extends Model
{
    protected $fillable = [
        'name',
        'business',
        'comment',
        'rating',
        'photo_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function payload(): array
    {
        return [
            ...$this->toArray(),
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
        ];
    }
}
