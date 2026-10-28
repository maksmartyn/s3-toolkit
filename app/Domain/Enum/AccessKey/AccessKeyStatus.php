<?php

declare(strict_types=1);

namespace App\Domain\Enum\AccessKey;

enum AccessKeyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Disabled'),
            self::Expired => __('Expired'),
        };
    }
}
