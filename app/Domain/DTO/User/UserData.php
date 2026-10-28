<?php

declare(strict_types=1);

namespace App\Domain\DTO\User;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\Enum\User\UserStatus;
use Carbon\CarbonImmutable;

final class UserData extends DTO
{
    public int $id;

    public string $name;

    public string $email;

    public bool $isSuperadmin;

    public bool $isActive;

    public bool $passwordChangeRequired;

    public UserStatus $status;

    /** @var list<SystemPermission> */
    public array $permissions;

    public ?CarbonImmutable $lastLoginAt;

    public ?CarbonImmutable $createdAt;

    public ?CarbonImmutable $deletedAt;

    protected array $casts = [
        'permissions' => SystemPermission::class,
    ];
}
