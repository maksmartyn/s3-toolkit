<?php

declare(strict_types=1);

namespace App\Domain\Enum\Audit;

enum AuditSubjectType: string
{
    case User = 'user';
    case PermissionTemplate = 'permission_template';
    case Storage = 'storage';
    case Bucket = 'bucket';

    public function label(): string
    {
        return match ($this) {
            self::User => __('User'),
            self::PermissionTemplate => __('Permission template'),
            self::Storage => __('Storage'),
            self::Bucket => __('Bucket'),
        };
    }
}
