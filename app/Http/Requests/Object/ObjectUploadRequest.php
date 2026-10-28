<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Services\Object\ObjectUploadService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Небольшой файл одним запросом; крупные загружаются частями (Multipart*Request).
 */
final class ObjectUploadRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::PutObjects;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'file' => ['required', 'file', 'max:'.(ObjectUploadService::PART_SIZE / 1024)],
        ];
    }

    public function filePath(): string
    {
        return (string) $this->file('file')?->getRealPath();
    }

    public function contentType(): ?string
    {
        $type = $this->file('file')?->getClientMimeType();

        return $type === null || $type === '' ? null : $type;
    }
}
