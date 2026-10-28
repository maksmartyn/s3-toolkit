<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessKey;

use App\Domain\Enum\Permission\StoragePermission;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Работа с ключами доступа хранилища — право «Управление ключами доступа».
 */
abstract class AccessKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('use', [$this->storage(), StoragePermission::ManageAccessKeys]);
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

    public function keyUser(): ?string
    {
        $user = $this->validated('user');

        return is_string($user) && $user !== '' ? $user : null;
    }
}
