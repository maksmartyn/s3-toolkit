<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\DTO\Bucket\ObjectLockData;
use App\Domain\Enum\Bucket\RetentionMode;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Включение Object Lock и срок хранения по умолчанию. mode = null — без срока по умолчанию.
 */
final class BucketObjectLockRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageObjectLock;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::ObjectLock;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', Rule::enum(RetentionMode::class)],
            'unit' => ['required_with:mode', 'nullable', 'in:days,years'],
            'period' => ['required_with:mode', 'nullable', 'integer', 'min:1', 'max:36500'],
        ];
    }

    public function toData(): ObjectLockData
    {
        $mode = RetentionMode::tryFrom((string) $this->validated('mode'));
        $period = $mode === null ? null : (int) $this->validated('period');

        return new ObjectLockData([
            'enabled' => true,
            'mode' => $mode,
            'days' => $this->validated('unit') === 'days' ? $period : null,
            'years' => $this->validated('unit') === 'years' ? $period : null,
        ]);
    }
}
