<?php

declare(strict_types=1);

namespace App\Domain\DTO\User;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Изменение имени и email: администратором в разделе пользователей или самим пользователем в профиле.
 */
final class UserUpdateData extends DTO
{
    public string $name;

    public string $email;
}
