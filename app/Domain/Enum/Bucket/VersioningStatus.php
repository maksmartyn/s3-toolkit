<?php

declare(strict_types=1);

namespace App\Domain\Enum\Bucket;

enum VersioningStatus: string
{
    /** Версионирование ни разу не включалось. Вернуться в это состояние после включения нельзя */
    case Unversioned = 'unversioned';
    case Enabled = 'enabled';
    case Suspended = 'suspended';

    public static function fromS3(?string $status): self
    {
        return match ($status) {
            'Enabled' => self::Enabled,
            'Suspended' => self::Suspended,
            default => self::Unversioned,
        };
    }

    /**
     * @return 'Enabled'|'Suspended'
     */
    public function toS3(): string
    {
        return $this === self::Enabled ? 'Enabled' : 'Suspended';
    }

    public function label(): string
    {
        return match ($this) {
            self::Unversioned => __('Not enabled'),
            self::Enabled => __('Enabled'),
            self::Suspended => __('Suspended'),
        };
    }
}
