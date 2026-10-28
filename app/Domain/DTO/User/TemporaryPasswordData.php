<?php

declare(strict_types=1);

namespace App\Domain\DTO\User;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Пользователь и выданный ему временный пароль. Пароль показывается один раз и нигде не хранится в открытом виде.
 */
final class TemporaryPasswordData extends DTO
{
    public UserData $user;

    public string $password;
}
