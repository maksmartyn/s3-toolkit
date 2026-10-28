<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;

final class ConnectionTestResultData extends DTO
{
    public bool $ok;

    public string $message;

    /** @var list<string> несколько найденных бакетов для наглядности */
    public array $buckets = [];

    public ?int $bucketCount = null;
}
