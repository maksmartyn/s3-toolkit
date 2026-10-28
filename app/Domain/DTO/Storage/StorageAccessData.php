<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\User\UserStatus;
use Carbon\CarbonImmutable;

/**
 * Права одного пользователя на хранилище.
 */
final class StorageAccessData extends DTO
{
    public int $userId;

    public string $userName;

    public string $userEmail;

    public UserStatus $userStatus;

    /** @var list<int> */
    public array $permissions;

    public ?CarbonImmutable $updatedAt;
}
