<?php

declare(strict_types=1);

namespace App\Domain\DTO\Audit;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Audit\AuditAction;

/**
 * Новая запись журнала. Автор, IP и источник берутся из AuditContext.
 */
final class AuditRecordData extends DTO
{
    public AuditAction $action;

    public ?int $subjectId = null;

    /** Название объекта на момент действия (имя пользователя, название шаблона) */
    public ?string $subjectLabel = null;

    /**
     * Подробности, см. AuditDetails. Секреты сюда не попадают.
     *
     * @var array<string, mixed>
     */
    public array $details = [];

    /**
     * Автор, если действие выполняется не в его запросе (например, фоновая операция, которую он запустил).
     * null — автор берётся из текущей сессии.
     */
    public ?int $actorId = null;
}
