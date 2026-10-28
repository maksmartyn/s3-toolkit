<?php

declare(strict_types=1);

namespace App\Domain\Enum\User;

enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Blocked => __('Blocked'),
            self::Deleted => __('Deleted'),
        };
    }
}
