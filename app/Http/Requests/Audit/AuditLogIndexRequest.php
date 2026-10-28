<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Domain\DTO\Audit\AuditFilterData;
use App\Domain\Enum\Audit\AuditAction;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\AuditLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('viewAny', AuditLogEntry::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function toData(): AuditFilterData
    {
        $date = fn (?string $value): ?CarbonImmutable => $value === null ? null : (CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null);

        return new AuditFilterData([
            'search' => $this->validated('search'),
            'action' => $this->validated('action'),
            'subjectUserId' => $this->validated('user_id') === null ? null : (int) $this->validated('user_id'),
            'dateFrom' => $date($this->validated('date_from')),
            'dateTo' => $date($this->validated('date_to')),
        ]);
    }
}
