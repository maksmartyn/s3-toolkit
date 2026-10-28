<?php

declare(strict_types=1);

namespace App\Domain\DTO\Audit;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Audit\AuditSubjectType;
use Carbon\CarbonImmutable;

final class AuditEntryData extends DTO
{
    public int $id;

    public CarbonImmutable $createdAt;

    public ?int $actorId;

    public ?string $actorName;

    public ?string $actorEmail;

    public AuditAction $action;

    public string $actionLabel;

    public AuditSubjectType $subjectType;

    public ?int $subjectId;

    public ?string $subjectLabel;

    /** @var list<string> подробности в читаемом виде */
    public array $lines;

    /** @var array<string, array{from: string|null, to: string|null}> документы «было/стало» для просмотра */
    public array $documents = [];

    public string $source;

    public ?string $ip;

    public ?string $userAgent;
}
