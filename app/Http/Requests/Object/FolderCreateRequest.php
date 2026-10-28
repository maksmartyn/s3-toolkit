<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

final class FolderCreateRequest extends ObjectRequest
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
            'prefix' => self::PREFIX,
            'name' => ['required', 'string', 'max:255', 'not_regex:/\\//'],
        ];
    }

    public function folder(): string
    {
        return $this->prefix().$this->validated('name').'/';
    }
}
