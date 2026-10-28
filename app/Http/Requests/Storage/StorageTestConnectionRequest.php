<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Domain\DTO\Storage\StorageSaveData;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Проверка подключения из формы. Для существующего хранилища (storage_id) пустые ключи берутся из сохранённых.
 */
final class StorageTestConnectionRequest extends FormRequest
{
    use StorageFormRules;

    public function authorize(): bool
    {
        $storage = $this->existing();

        return $storage === null
            ? $this->authUser()->can('create', Storage::class)
            : $this->authUser()->can('update', $storage);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->storageRules($this->existing());
        // Имя для проверки подключения не нужно
        $rules['name'] = ['nullable', 'string', 'max:255'];

        return [...$rules, 'storage_id' => ['nullable', 'integer']];
    }

    public function existing(): ?Storage
    {
        $id = $this->input('storage_id');

        // Несуществующий id — как новое хранилище: по ответу нельзя перебирать id
        return $id === null ? null : Storage::query()->find((int) $id);
    }

    public function toData(): StorageSaveData
    {
        $data = $this->storageData();

        return $data->with(['name' => $data->name !== '' ? $data->name : '—']);
    }
}
