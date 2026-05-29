<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailNotification extends Model
{
    use HasFactory;

    public const TYPE_CARD_PURCHASE_PAID = 'card_purchase_paid';

    public const TYPE_WELCOME_USER = 'welcome_user';

    public const TYPE_CARD_SENT_TO_PRINT = 'card_sent_to_print';

    public const TYPE_PASSWORD_CHANGED = 'password_changed';

    public const TYPE_VIRTUAL_CARD_VISIT_MILESTONE = 'virtual_card_visit_milestone';

    protected $fillable = [
        'user_id',
        'card_id',
        'link_page_id',
        'order_id',
        'type',
        'milestone',
        'metadata',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
