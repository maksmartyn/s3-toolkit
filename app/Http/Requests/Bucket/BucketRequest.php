<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use App\Services\Storage\StorageCapabilities;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Действие над бакетами хранилища. Наследник задаёт нужное право хранилища.
 */
abstract class BucketRequest extends FormRequest
{
    abstract protected function permission(): StoragePermission;

    /**
     * Функция хранилища, без которой действие недоступно (интерфейс его не показывает).
     */
    protected function capability(): ?StorageCapability
    {
        return null;
    }

    public function authorize(): bool
    {
        $capability = $this->capability();

        return $this->authUser()->can('use', [$this->storage(), $this->permission()])
            && ($capability === null || app(StorageCapabilities::class)->has($this->storage(), $capability));
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
