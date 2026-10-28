<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Services\Object\ObjectUploadService;
use Illuminate\Contracts\Validation\ValidationRule;

final class MultipartPartRequest extends ObjectRequest
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
            'upload_id' => ['required', 'string', 'max:1024'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'file' => ['required', 'file', 'max:'.(ObjectUploadService::PART_SIZE / 1024 + 1)],
        ];
    }

    public function filePath(): string
    {
        return (string) $this->file('file')?->getRealPath();
    }
}
