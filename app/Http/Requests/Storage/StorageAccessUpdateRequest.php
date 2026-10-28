<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Http\Requests\Concerns\ValidatesPermissions;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class StorageAccessUpdateRequest extends StorageActionRequest
{
    use ValidatesPermissions;

    protected function ability(): string
    {
        return 'manageAccess';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')->withoutTrashed()],
            ...$this->permissionRules(PermissionScope::Storage),
        ];
    }

    public function userId(): int
    {
        return (int) $this->validated('user_id');
    }

    /**
     * @return PermissionMask<StoragePermission>
     */
    public function toMask(): PermissionMask
    {
        /** @var PermissionMask<StoragePermission> */
        return $this->permissionMask(PermissionScope::Storage);
    }
}
