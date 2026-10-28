<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

final class ObjectRenameRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::PutObjects;
    }

    protected function additionalPermissions(): array
    {
        return [StoragePermission::ViewObjects, StoragePermission::DeleteObjects];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'new_key' => ['required', 'string', 'max:1024', 'different:key'],
        ];
    }
}
