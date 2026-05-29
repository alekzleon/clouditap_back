<?php

namespace App\Enums;

enum Plan: string
{
    case Basic = 'basic';
    case Pro = 'pro';
    case Company = 'company';

    public function cardLimit(): ?int
    {
        return match ($this) {
            self::Basic => 1,
            self::Pro => 5,
            self::Company => null,
        };
    }

    public function profileLinkLimit(): ?int
    {
        return match ($this) {
            self::Basic => 5,
            self::Pro => 25,
            self::Company => null,
        };
    }

    public function bannerLimit(): int
    {
        return match ($this) {
            self::Basic => 2,
            self::Pro, self::Company => 10,
        };
    }

    public function hasAdvancedAnalytics(): bool
    {
        return match ($this) {
            self::Basic => false,
            self::Pro, self::Company => true,
        };
    }

    public function canCreateCards(int $currentCards): bool
    {
        $limit = $this->cardLimit();

        return $limit === null || $currentCards < $limit;
    }
}
