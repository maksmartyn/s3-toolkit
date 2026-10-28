<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

final class StorageShowRequest extends StorageActionRequest
{
    protected function ability(): string
    {
        return 'view';
    }
}
