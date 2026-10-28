<?php

declare(strict_types=1);

namespace App\Domain\DTO\User;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\User\UserStatus;

final class UserFilterData extends DTO
{
    public ?string $search = null;

    /** null — активные и заблокированные, без удалённых. */
    public ?UserStatus $status = null;

    public int $perPage = 20;
}
