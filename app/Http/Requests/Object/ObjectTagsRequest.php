<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Теги объекта: S3 позволяет не больше 10.
 */
final class ObjectTagsRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::PutObjects;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Tagging;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*.key' => ['required', 'string', 'max:128', 'distinct', 'not_regex:/^aws:/i'],
            'tags.*.value' => ['nullable', 'string', 'max:256'],
        ];
    }

    /**
     * @return list<BucketTagData>
     */
    public function tags(): array
    {
        return array_values(array_map(
            fn (array $tag) => new BucketTagData(['key' => (string) $tag['key'], 'value' => (string) ($tag['value'] ?? '')]),
            (array) ($this->validated('tags') ?? []),
        ));
    }
}
