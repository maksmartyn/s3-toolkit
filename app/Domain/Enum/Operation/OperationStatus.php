<?php

declare(strict_types=1);

namespace App\Domain\Enum\Operation;

enum OperationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Queued, self::Running];
    }
}
