<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Копирование или перемещение выбранных объектов в «папку» этого или другого бакета хранилища.
 */
final class ObjectTransferRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::PutObjects;
    }

    protected function additionalPermissions(): array
    {
        return $this->boolean('move')
            ? [StoragePermission::ViewObjects, StoragePermission::DeleteObjects]
            : [StoragePermission::ViewObjects];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'min:1', 'max:100'],
            'keys.*' => self::KEY,
            'source_prefix' => self::PREFIX,
            'target_bucket' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/'],
            'target_prefix' => self::PREFIX,
            'move' => ['boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('keys')));
    }
}
