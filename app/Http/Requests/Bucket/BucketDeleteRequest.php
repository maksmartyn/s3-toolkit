<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Удаление бакета подтверждается вводом его точного имени.
 */
final class BucketDeleteRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::DeleteBuckets;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:'.$this->bucket()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirmation.in' => __('Enter the exact bucket name to confirm.')];
    }
}
