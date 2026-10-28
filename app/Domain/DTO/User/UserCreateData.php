<?php

declare(strict_types=1);

namespace App\Domain\DTO\User;

use App\Domain\DTO\Abstracts\DTO;

final class UserCreateData extends DTO
{
    public string $name;

    public string $email;

    /** Если не задан, будет сгенерирован временный пароль. */
    public ?string $password = null;
}
