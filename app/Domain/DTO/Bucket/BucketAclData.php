<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Bucket\CannedAcl;

final class BucketAclData extends DTO
{
    public ?string $owner = null;

    /** @var list<array{grantee: string, type: string, permission: string}> */
    public array $grants = [];

    /** Готовый набор, которому соответствуют гранты; null — настроены вручную */
    public ?CannedAcl $canned = null;
}
