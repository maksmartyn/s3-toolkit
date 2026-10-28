<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Bucket\RetentionMode;

/**
 * Object Lock бакета и срок хранения по умолчанию для новых объектов (дни или годы).
 */
final class ObjectLockData extends DTO
{
    public bool $enabled = false;

    public ?RetentionMode $mode = null;

    public ?int $days = null;

    public ?int $years = null;
}
