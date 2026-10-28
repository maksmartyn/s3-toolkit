<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Содержимое «папки»: поиск по началу имени и маркер следующей страницы.
 */
final class ObjectListRequest extends ObjectRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ViewObjects;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prefix' => self::PREFIX,
            'search' => ['nullable', 'string', 'max:1024'],
            'token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function search(): string
    {
        return (string) $this->validated('search');
    }

    public function token(): ?string
    {
        $token = $this->validated('token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
