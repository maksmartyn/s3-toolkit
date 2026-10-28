<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

final class StorageDeleteRequest extends StorageActionRequest
{
    protected function ability(): string
    {
        return 'delete';
    }
}
