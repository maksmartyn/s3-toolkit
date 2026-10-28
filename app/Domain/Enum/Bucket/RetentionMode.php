<?php

declare(strict_types=1);

namespace App\Domain\Enum\Bucket;

enum RetentionMode: string
{
    /** Пользователи с особым правом могут снять защиту */
    case Governance = 'GOVERNANCE';

    /** Защиту не может снять никто, включая владельца аккаунта */
    case Compliance = 'COMPLIANCE';

    public function label(): string
    {
        return match ($this) {
            self::Governance => __('Governance'),
            self::Compliance => __('Compliance'),
        };
    }
}
