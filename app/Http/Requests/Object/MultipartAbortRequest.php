<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

final class MultipartAbortRequest extends ObjectRequest
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
        ];
    }
}
