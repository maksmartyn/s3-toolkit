<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Список бакетов или ошибка хранилища: страница хранилища не должна падать, если оно недоступно.
 */
final class BucketListData extends DTO
{
    /** @var list<BucketData> */
    public array $buckets = [];

    public ?string $error = null;
}
