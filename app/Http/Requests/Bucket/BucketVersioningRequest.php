<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Bucket\VersioningStatus;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class BucketVersioningRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageVersioning;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Versioning;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in([VersioningStatus::Enabled->value, VersioningStatus::Suspended->value])]];
    }

    public function status(): VersioningStatus
    {
        return VersioningStatus::from((string) $this->validated('status'));
    }
}
