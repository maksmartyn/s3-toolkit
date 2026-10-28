<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Audit\AuditSubjectType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Запись журнала. Журнал только пополняется: изменение и удаление записей запрещены.
 *
 * @property int $id
 * @property CarbonInterface $created_at
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string|null $actor_email
 * @property AuditAction $action
 * @property AuditSubjectType $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $details
 * @property string $source
 * @property string|null $ip
 * @property string|null $user_agent
 */
#[Fillable([
    'actor_id', 'actor_name', 'actor_email', 'action', 'subject_type', 'subject_id',
    'subject_label', 'details', 'source', 'ip', 'user_agent',
])]
class AuditLogEntry extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'audit_log';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'action' => AuditAction::class,
            'subject_type' => AuditSubjectType::class,
            'details' => 'array',
        ];
    }
}
