<?php

declare(strict_types=1);

namespace App\Domain\Enum\Operation;

/**
 * Значения хранятся в БД — не переименовывать.
 */
enum OperationType: string
{
    case BucketDelete = 'bucket_delete';
    case BucketStats = 'bucket_stats';

    public function label(): string
    {
        return match ($this) {
            self::BucketDelete => __('Deleting bucket'),
            self::BucketStats => __('Calculating statistics'),
        };
    }
}
