<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\DTO\Bucket\CorsRuleData;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правила CORS; пустой список — удалить конфигурацию CORS.
 */
final class BucketCorsRequest extends BucketRequest
{
    public const array METHODS = ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'];

    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageCors;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Cors;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rules' => ['nullable', 'array', 'max:100'],
            'rules.*.id' => ['nullable', 'string', 'max:255'],
            'rules.*.allowed_origins' => ['required', 'array', 'min:1'],
            // Пустые строки превращаются в null (ConvertEmptyStringsToNull) и затем отбрасываются
            'rules.*.allowed_origins.*' => ['nullable', 'string', 'max:1024'],
            'rules.*.allowed_methods' => ['required', 'array', 'min:1'],
            'rules.*.allowed_methods.*' => ['string', 'distinct', 'in:'.implode(',', self::METHODS)],
            'rules.*.allowed_headers' => ['nullable', 'array'],
            'rules.*.allowed_headers.*' => ['nullable', 'string', 'max:1024'],
            'rules.*.expose_headers' => ['nullable', 'array'],
            'rules.*.expose_headers.*' => ['nullable', 'string', 'max:1024'],
            'rules.*.max_age_seconds' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /**
     * @return list<CorsRuleData>
     */
    public function toData(): array
    {
        $clean = fn (mixed $values): array => array_values(array_filter(array_map(
            fn (mixed $value) => trim((string) $value),
            (array) ($values ?? []),
        ), fn (string $value) => $value !== ''));

        return array_values(array_map(fn (array $rule) => new CorsRuleData([
            'id' => isset($rule['id']) && $rule['id'] !== '' ? (string) $rule['id'] : null,
            'allowedOrigins' => $clean($rule['allowed_origins'] ?? []),
            'allowedMethods' => $clean($rule['allowed_methods'] ?? []),
            'allowedHeaders' => $clean($rule['allowed_headers'] ?? []),
            'exposeHeaders' => $clean($rule['expose_headers'] ?? []),
            'maxAgeSeconds' => isset($rule['max_age_seconds']) ? (int) $rule['max_age_seconds'] : null,
        ]), (array) ($this->validated('rules') ?? [])));
    }
}
