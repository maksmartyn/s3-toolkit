<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Domain\DTO\Storage\StorageSaveData;
use Illuminate\Contracts\Validation\ValidationRule;

final class StorageUpdateRequest extends StorageActionRequest
{
    use StorageFormRules;

    protected function ability(): string
    {
        return 'update';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->storageRules($this->storage());
    }

    public function toData(): StorageSaveData
    {
        return $this->storageData();
    }
}
