<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

final class ObjectLegalHoldRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageObjectLock;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::ObjectLock;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'version' => self::VERSION,
            'on' => ['required', 'boolean'],
        ];
    }
}
