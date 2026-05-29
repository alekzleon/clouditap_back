<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TapPricingPlan extends Model
{
    protected $fillable = [
        'name',
        'price_label',
        'badge',
        'description',
        'features',
        'button_text',
        'button_url',
        'is_featured',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
