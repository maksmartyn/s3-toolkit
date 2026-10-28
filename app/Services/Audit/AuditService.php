<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Domain\DTO\Audit\AuditEntryData;
use App\Domain\DTO\Audit\AuditFilterData;
use App\Domain\DTO\Audit\AuditRecordData;
use App\Models\AuditLogEntry;
use App\Repositories\Audit\AuditLogRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Журнал действий. Записывать нужно в той же транзакции, что и само изменение.
 */
final readonly class AuditService
{
    public function __construct(
        private AuditLogRepositoryInterface $entries,
        private AuditContext $context,
        private AuditDetailsFormatter $formatter,
        private UserRepositoryInterface $users,
    ) {}

    public function record(AuditRecordData $data): void
    {
        $actor = $data->actorId !== null
            ? $this->users->findWithTrashedOrFail($data->actorId)
            : $this->context->actor();

        $this->entries->append([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => $data->action,
            'subject_type' => $data->action->subjectType(),
            'subject_id' => $data->subjectId,
            'subject_label' => $data->subjectLabel,
            'details' => $data->details === [] ? null : $data->details,
            'source' => $this->context->source(),
            'ip' => $this->context->ip(),
            'user_agent' => $this->context->userAgent(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, AuditEntryData>
     */
    public function paginate(AuditFilterData $filter): LengthAwarePaginator
    {
        return $this->entries->paginateFiltered($filter)->through($this->toData(...));
    }

    private function toData(AuditLogEntry $entry): AuditEntryData
    {
        return new AuditEntryData([
            'id' => $entry->id,
            'createdAt' => $entry->created_at->toImmutable(),
            'actorId' => $entry->actor_id,
            'actorName' => $entry->actor_name,
            'actorEmail' => $entry->actor_email,
            'action' => $entry->action,
            'actionLabel' => $entry->action->label(),
            'subjectType' => $entry->subject_type,
            'subjectId' => $entry->subject_id,
            'subjectLabel' => $entry->subject_label,
            'lines' => $this->formatter->lines($entry->details),
            'source' => $entry->source,
            'ip' => $entry->ip,
            'userAgent' => $entry->user_agent,
        ]);
    }
}
