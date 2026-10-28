<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Domain\DTO\Storage\StorageSaveData;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

final class StorageStoreRequest extends FormRequest
{
    use StorageFormRules;

    public function authorize(): bool
    {
        return $this->authUser()->can('create', Storage::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->storageRules(null);
    }

    public function toData(): StorageSaveData
    {
        return $this->storageData();
    }
}
