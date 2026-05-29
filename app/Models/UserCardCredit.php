<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCardCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purchased',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'purchased' => 'integer',
            'used' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function available(): int
    {
        return max(0, $this->purchased - $this->used);
    }
}
