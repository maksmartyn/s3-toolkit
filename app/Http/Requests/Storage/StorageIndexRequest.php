<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\Storage;
use Illuminate\Contracts\Validation\ValidationRule;

final class StorageIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('viewAny', Storage::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
