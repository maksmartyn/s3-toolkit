<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Действие над бакетами хранилища. Наследник задаёт нужное право хранилища.
 */
abstract class BucketRequest extends FormRequest
{
    abstract protected function permission(): StoragePermission;

    public function authorize(): bool
    {
        return $this->authUser()->can('use', [$this->storage(), $this->permission()]);
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

    public function bucket(): string
    {
        return (string) $this->route('bucket');
    }
}
