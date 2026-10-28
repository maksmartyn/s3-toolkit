<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Отмена незавершённых multipart-загрузок (любых, не только своих).
 */
final class UploadAbortRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::DeleteObjects;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::MultipartUploads;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uploads' => ['required', 'array', 'min:1', 'max:100'],
            'uploads.*.key' => self::KEY,
            'uploads.*.upload_id' => ['required', 'string', 'max:1024'],
        ];
    }

    /**
     * @return list<array{key: string, uploadId: string}>
     */
    public function uploads(): array
    {
        return array_values(array_map(
            fn (array $upload) => ['key' => (string) $upload['key'], 'uploadId' => (string) $upload['upload_id']],
            (array) $this->validated('uploads'),
        ));
    }
}
