<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\DTO\Object\ObjectMetadataData;
use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Стандартные заголовки и пользовательские метаданные (x-amz-meta-*). Объект перезаписывается копированием в себя.
 */
final class ObjectMetadataRequest extends ObjectRequest
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
            'content_type' => ['nullable', 'string', 'max:255'],
            'cache_control' => ['nullable', 'string', 'max:255'],
            'content_disposition' => ['nullable', 'string', 'max:1024'],
            'metadata' => ['nullable', 'array', 'max:50'],
            'metadata.*.key' => ['required', 'string', 'max:128', 'distinct', 'regex:/^[A-Za-z0-9_-]+$/'],
            'metadata.*.value' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function toData(): ObjectMetadataData
    {
        $metadata = [];

        foreach ((array) ($this->validated('metadata') ?? []) as $item) {
            $metadata[strtolower((string) $item['key'])] = (string) ($item['value'] ?? '');
        }

        return new ObjectMetadataData([
            'contentType' => $this->validated('content_type') ?: null,
            'cacheControl' => $this->validated('cache_control') ?: null,
            'contentDisposition' => $this->validated('content_disposition') ?: null,
            'metadata' => $metadata,
        ]);
    }
}
