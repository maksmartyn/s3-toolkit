<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessKey;

use App\Http\Requests\Storage\StorageActionRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Учётные данные управления ключами меняет тот, кто управляет хранилищами: они дают больше прав, чем ключи хранилища.
 */
final class KeyManagementCredentialsRequest extends StorageActionRequest
{
    protected function ability(): string
    {
        return 'update';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'credentials' => ['present', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function credentials(): array
    {
        return array_map(fn (mixed $value) => is_string($value) ? $value : null, (array) $this->validated('credentials'));
    }
}
