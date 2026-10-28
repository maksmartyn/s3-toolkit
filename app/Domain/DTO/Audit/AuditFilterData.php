<?php

declare(strict_types=1);

namespace App\Domain\DTO\Audit;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Audit\AuditAction;
use Carbon\CarbonImmutable;

final class AuditFilterData extends DTO
{
    /** Поиск по автору (имя, email) и названию объекта */
    public ?string $search = null;

    public ?AuditAction $action = null;

    public ?int $subjectUserId = null;

    public ?CarbonImmutable $dateFrom = null;

    public ?CarbonImmutable $dateTo = null;

    public int $perPage = 50;
}
