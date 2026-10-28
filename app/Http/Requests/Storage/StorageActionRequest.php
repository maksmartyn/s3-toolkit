<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Действие над существующим хранилищем. Наследник задаёт способность из StoragePolicy.
 */
abstract class StorageActionRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return $this->authUser()->can($this->ability(), $this->storage());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function storage(): Storage
    {
        /** @var Storage */
        return $this->route('storage');
    }
}
