<?php

namespace App\Enums;

enum CardStatus: string
{
    case Draft = 'draft';
    case Designing = 'designing';
    case Ordered = 'ordered';
    case Printing = 'printing';
    case Shipped = 'shipped';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Designing => 'En diseño',
            self::Ordered => 'Ordenada',
            self::Printing => 'En impresión',
            self::Shipped => 'Enviada',
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
        };
    }

    public function isPubliclyAccessible(): bool
    {
        return $this === self::Active;
    }
}
