<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessKey;

use Illuminate\Contracts\Validation\ValidationRule;

final class AccessKeyDeleteRequest extends AccessKeyRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'user' => ['nullable', 'string', 'max:255'],
        ];
    }
}
