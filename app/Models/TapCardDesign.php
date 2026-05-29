<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TapCardDesign extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function payload(): array
    {
        return [
            ...$this->toArray(),
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        ];
    }
}
