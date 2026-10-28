<?php

declare(strict_types=1);

namespace App\Repositories\Audit;

use App\Domain\DTO\Audit\AuditFilterData;
use App\Models\AuditLogEntry;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Журнал только пополняется, поэтому здесь нет update и delete из базового репозитория.
 */
interface AuditLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): AuditLogEntry;

    /**
     * @return LengthAwarePaginator<int, AuditLogEntry>
     */
    public function paginateFiltered(AuditFilterData $filter): LengthAwarePaginator;
}
