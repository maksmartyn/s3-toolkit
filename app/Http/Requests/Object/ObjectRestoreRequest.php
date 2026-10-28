<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Восстановление старой версии: её копия становится текущей.
 */
final class ObjectRestoreRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::PutObjects;
    }

    protected function additionalPermissions(): array
    {
        return [StoragePermission::ViewObjects];
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Versioning;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'version' => ['required', 'string', 'max:1024'],
        ];
    }
}
