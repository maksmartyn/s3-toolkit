<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Bucket\CannedAcl;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class BucketAclRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageAcl;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Acl;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['acl' => ['required', Rule::enum(CannedAcl::class)]];
    }

    public function acl(): CannedAcl
    {
        return CannedAcl::from((string) $this->validated('acl'));
    }
}
