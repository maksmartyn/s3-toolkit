<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use App\Rules\BucketPolicyRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Пустая политика — удалить политику бакета.
 */
final class BucketPolicyRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageBucketPolicy;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::BucketPolicy;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['policy' => ['nullable', 'string', 'max:20480', 'json', new BucketPolicyRule]];
    }

    public function policy(): ?string
    {
        $policy = $this->validated('policy');

        return is_string($policy) && trim($policy) !== '' ? $policy : null;
    }
}
