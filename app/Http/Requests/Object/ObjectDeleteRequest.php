<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\DTO\Object\ObjectReferenceData;
use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Удаление объектов или конкретных версий (до 1000 за раз — ограничение S3).
 */
final class ObjectDeleteRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::DeleteObjects;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'objects' => ['required', 'array', 'min:1', 'max:1000'],
            'objects.*.key' => self::KEY,
            'objects.*.version' => self::VERSION,
        ];
    }

    /**
     * @return list<ObjectReferenceData>
     */
    public function objects(): array
    {
        return array_values(array_map(fn (array $object) => new ObjectReferenceData([
            'key' => (string) $object['key'],
            'versionId' => isset($object['version']) && $object['version'] !== '' ? (string) $object['version'] : null,
        ]), (array) $this->validated('objects')));
    }
}
