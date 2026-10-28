<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Domain\DTO\Storage\StorageSaveData;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

/**
 * Правила формы хранилища. На изменение ключи необязательны: пустые — оставить текущие, но менять только парой.
 */
trait StorageFormRules
{
    /**
     * @return array<string, array<int, string|Enum|RequiredIf|Unique>>
     */
    protected function storageRules(?Storage $storage): array
    {
        $provider = StorageProvider::tryFrom((string) $this->input('provider'));
        $keysRequired = $storage === null ? 'required' : 'required_with:access_key_id,secret_access_key';

        $unique = Rule::unique(Storage::class, 'name');

        return [
            'name' => ['required', 'string', 'max:255', $storage === null ? $unique : $unique->ignore($storage->id)],
            'provider' => ['required', Rule::enum(StorageProvider::class)],
            'endpoint' => [
                Rule::requiredIf(fn (): bool => $provider?->endpointRequired() ?? true),
                'nullable',
                'string',
                'max:255',
                'url:http,https',
                'not_regex:/[{}]/',
            ],
            'region' => ['required', 'string', 'max:64'],
            'use_path_style' => ['boolean'],
            'verify_ssl' => ['boolean'],
            'access_key_id' => [$keysRequired, 'nullable', 'string', 'max:512'],
            'secret_access_key' => [$keysRequired, 'nullable', 'string', 'max:1024'],
            'buckets' => ['nullable', 'array', 'max:100'],
            'buckets.*' => ['string', 'distinct', 'min:1', 'max:255', 'regex:/^[^\s\/]+$/'],
        ];
    }

    protected function storageData(): StorageSaveData
    {
        /** @var list<string> $buckets */
        $buckets = array_values(array_filter((array) ($this->validated('buckets') ?? [])));

        return new StorageSaveData([
            'name' => (string) $this->validated('name'),
            'provider' => (string) $this->validated('provider'),
            'endpoint' => $this->validated('endpoint') ?: null,
            'region' => (string) $this->validated('region'),
            'usePathStyle' => (bool) $this->validated('use_path_style', false),
            'verifySsl' => (bool) $this->validated('verify_ssl', true),
            'accessKeyId' => $this->validated('access_key_id') ?: null,
            'secretAccessKey' => $this->validated('secret_access_key') ?: null,
            'buckets' => $buckets === [] ? null : $buckets,
        ]);
    }

    protected function prepareForValidation(): void
    {
        // Бакеты из textarea: по одному на строку
        if (is_string($this->input('buckets'))) {
            $this->merge(['buckets' => array_values(array_filter(array_map(trim(...), preg_split('/\R/', $this->input('buckets')) ?: [])))]);
        }
    }
}
