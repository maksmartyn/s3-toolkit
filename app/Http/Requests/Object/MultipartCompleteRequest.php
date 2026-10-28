<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

final class MultipartCompleteRequest extends ObjectRequest
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
            'parts' => ['required', 'array', 'min:1', 'max:10000'],
            'parts.*.part_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<array{partNumber: int, etag: string}>
     */
    public function parts(): array
    {
        return array_values(array_map(
            fn (array $part) => ['partNumber' => (int) $part['part_number'], 'etag' => trim((string) $part['etag'], '"')],
            (array) $this->validated('parts'),
        ));
    }
}
