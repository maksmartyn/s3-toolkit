<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Bucket\RetentionMode;
use Carbon\CarbonImmutable;

/**
 * Защита версии объекта (Object Lock): срок хранения и бессрочное удержание (legal hold).
 */
final class ObjectProtectionData extends DTO
{
    public ?RetentionMode $mode = null;

    public ?CarbonImmutable $until = null;

    public bool $legalHold = false;
}
