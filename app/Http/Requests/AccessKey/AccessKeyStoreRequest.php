<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessKey;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

final class AccessKeyStoreRequest extends AccessKeyRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // MinIO ограничивает имя 32 символами
            'name' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:256'],
            'user' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'policy' => ['nullable', 'string', 'json', 'max:20480'],
        ];
    }

    public function toData(): AccessKeyCreateData
    {
        $text = fn (string $field): ?string => is_string($this->validated($field)) && trim($this->validated($field)) !== ''
            ? trim($this->validated($field))
            : null;

        return new AccessKeyCreateData([
            'name' => $text('name'),
            'description' => $text('description'),
            'user' => $text('user'),
            'expiresAt' => $text('expires_at') === null ? null : CarbonImmutable::parse((string) $text('expires_at'))->utc(),
            'policy' => $text('policy'),
        ]);
    }
}
