<?php

declare(strict_types=1);

namespace App\Repositories\Audit;

use App\Domain\DTO\Audit\AuditFilterData;
use App\Domain\Enum\Audit\AuditSubjectType;
use App\Models\AuditLogEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function append(array $attributes): AuditLogEntry
    {
        return AuditLogEntry::query()->create($attributes);
    }

    public function paginateFiltered(AuditFilterData $filter): LengthAwarePaginator
    {
        $query = AuditLogEntry::query();

        if ($filter->action !== null) {
            $query->where('action', $filter->action);
        }

        if ($filter->subjectUserId !== null) {
            $query->where('subject_type', AuditSubjectType::User)->where('subject_id', $filter->subjectUserId);
        }

        if ($filter->dateFrom !== null) {
            $query->where('created_at', '>=', $filter->dateFrom->startOfDay());
        }

        if ($filter->dateTo !== null) {
            $query->where('created_at', '<=', $filter->dateTo->endOfDay());
        }

        if ($filter->search !== null && $filter->search !== '') {
            $search = '%'.addcslashes($filter->search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('actor_name', 'like', $search)
                ->orWhere('actor_email', 'like', $search)
                ->orWhere('subject_label', 'like', $search));
        }

        return $query->orderByDesc('id')->paginate($filter->perPage)->withQueryString();
    }
}
