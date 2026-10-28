<?php

declare(strict_types=1);

namespace App\Http\Requests\Object;

use App\Domain\Enum\Bucket\RetentionMode;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class ObjectRetentionRequest extends ObjectRequest
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
            'key' => self::KEY,
            'version' => self::VERSION,
            'mode' => ['required', Rule::enum(RetentionMode::class)],
            'until' => ['required', 'date', 'after:now'],
        ];
    }

    public function mode(): RetentionMode
    {
        return RetentionMode::from((string) $this->validated('mode'));
    }

    public function until(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('until'))->utc();
    }
}
