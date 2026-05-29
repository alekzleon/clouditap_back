<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->card_id,
            'from_status' => $this->from_status,
            'from_status_label' => $this->statusLabel($this->from_status),
            'to_status' => $this->to_status,
            'to_status_label' => $this->statusLabel($this->to_status),
            'note' => $this->note,
            'created_at' => $this->created_at,
            'actor' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
        ];
    }

    private function statusLabel(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        return CardStatus::tryFrom($status)?->label() ?? $status;
    }
}
