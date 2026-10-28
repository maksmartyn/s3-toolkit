<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\DTO\Bucket\BucketCreateData;
use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

final class BucketStoreRequest extends BucketRequest
{
    /**
     * Правила имён S3: 3–63 символа, строчные буквы, цифры, точки и дефисы, не похоже на IP-адрес.
     */
    public const string NAME_PATTERN = '/^(?!xn--)(?!.*\.\.)(?!\d+\.\d+\.\d+\.\d+$)[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/';

    protected function permission(): StoragePermission
    {
        return StoragePermission::CreateBuckets;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:'.self::NAME_PATTERN],
            'object_lock' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('3–63 characters: lowercase letters, digits, dots and hyphens; must start and end with a letter or digit.'),
        ];
    }

    public function toData(): BucketCreateData
    {
        return new BucketCreateData([
            'name' => (string) $this->validated('name'),
            'objectLock' => (bool) $this->validated('object_lock', false),
        ]);
    }
}
