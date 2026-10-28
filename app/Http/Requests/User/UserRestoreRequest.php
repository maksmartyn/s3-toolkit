<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

final class UserRestoreRequest extends UserActionRequest
{
    protected function ability(): string
    {
        return 'restore';
    }
}
