<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

final class UploadListRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ViewObjects;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::MultipartUploads;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
        ];
    }
}
