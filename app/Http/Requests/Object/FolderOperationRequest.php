<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Фоновая операция над «папкой» целиком: удаление (право на удаление), копирование (запись), перемещение (оба).
 */
final class FolderOperationRequest extends ObjectRequest
{
    protected function additionalPermissions(): array
    {
        return match ($this->input('type')) {
            'move' => [StoragePermission::ViewObjects, StoragePermission::DeleteObjects],
            'copy' => [StoragePermission::ViewObjects],
            default => [],
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:delete,copy,move'],
            'prefix' => ['required', 'string', 'max:1024'],
            'target_bucket' => ['required_unless:type,delete', 'nullable', 'string', 'regex:/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/'],
            'target_prefix' => self::PREFIX,
        ];
    }

    protected function permission(): StoragePermission
    {
        return $this->input('type') === 'delete' ? StoragePermission::DeleteObjects : StoragePermission::PutObjects;
    }

    public function type(): OperationType
    {
        return match ($this->validated('type')) {
            'delete' => OperationType::FolderDelete,
            'move' => OperationType::FolderMove,
            default => OperationType::FolderCopy,
        };
    }
}
