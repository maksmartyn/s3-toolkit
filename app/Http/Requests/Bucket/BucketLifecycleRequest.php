<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Правила жизненного цикла в формате S3 API (их собирает и форма, и JSON-редактор). Пустой список — удалить конфигурацию.
 */
final class BucketLifecycleRequest extends BucketRequest
{
    private const array ACTIONS = [
        'Expiration', 'NoncurrentVersionExpiration', 'AbortIncompleteMultipartUpload', 'Transitions', 'NoncurrentVersionTransitions',
    ];

    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageLifecycle;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Lifecycle;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'rules' => ['nullable', 'array', 'max:1000'],
            'rules.*' => ['array', function (string $attribute, mixed $rule, Closure $fail): void {
                if (is_array($rule) && array_intersect(self::ACTIONS, array_keys($rule)) === []) {
                    $fail(__('Each rule must contain at least one action (expiration, transition or upload cleanup).'));
                }
            }],
            'rules.*.ID' => ['nullable', 'string', 'max:255'],
            'rules.*.Status' => ['required', 'in:Enabled,Disabled'],
            'rules.*.Expiration.Days' => ['nullable', 'integer', 'min:1'],
            'rules.*.NoncurrentVersionExpiration.NoncurrentDays' => ['nullable', 'integer', 'min:1'],
            'rules.*.AbortIncompleteMultipartUpload.DaysAfterInitiation' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Правила для S3: старое поле Prefix переносится в Filter, без фильтра правило действует на весь бакет.
     *
     * @return list<array<string, mixed>>
     */
    public function toRules(): array
    {
        return array_values(array_map(function (array $rule): array {
            if (! isset($rule['Filter'])) {
                $rule['Filter'] = ['Prefix' => (string) ($rule['Prefix'] ?? '')];
            }

            unset($rule['Prefix']);

            if (($rule['ID'] ?? null) === null || $rule['ID'] === '') {
                unset($rule['ID']);
            }

            return $rule;
        }, (array) ($this->input('rules') ?? [])));
    }
}
