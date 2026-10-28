<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ограничения S3: до 50 тегов, ключ до 128 символов, значение до 256, префикс aws: зарезервирован.
 */
final class BucketTagsRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageBucketTags;
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
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*.key' => ['required', 'string', 'max:128', 'distinct', 'not_regex:/^aws:/i'],
            'tags.*.value' => ['nullable', 'string', 'max:256'],
        ];
    }

    /**
     * @return list<BucketTagData>
     */
    public function toData(): array
    {
        return array_values(array_map(
            fn (array $tag) => new BucketTagData(['key' => (string) $tag['key'], 'value' => (string) ($tag['value'] ?? '')]),
            (array) ($this->validated('tags') ?? []),
        ));
    }
}
