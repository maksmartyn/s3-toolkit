<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Временная ссылка: срок в секундах (S3 позволяет не больше 7 суток), attachment — скачивать как файл.
 */
final class ObjectShareRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::SharePresignedLinks;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => self::KEY,
            'version' => self::VERSION,
            'expires' => ['required', 'integer', 'min:60', 'max:604800'],
            'attachment' => ['boolean'],
        ];
    }
}
