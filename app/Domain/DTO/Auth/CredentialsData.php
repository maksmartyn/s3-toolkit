<?php

declare(strict_types=1);

namespace App\Domain\DTO\Auth;

use App\Domain\DTO\Abstracts\DTO;

final class CredentialsData extends DTO
{
    public string $email;

    public string $password;
}
