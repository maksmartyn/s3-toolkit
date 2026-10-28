<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\DTO\Bucket\BucketTagData;
use Carbon\CarbonImmutable;

final class ObjectDetailsData extends DTO
{
    public string $key;

    public int $size;

    public ?string $contentType = null;

    public ?string $cacheControl = null;

    public ?string $contentDisposition = null;

    public ?string $contentEncoding = null;

    public ?string $etag = null;

    public ?CarbonImmutable $lastModified = null;

    public ?string $storageClass = null;

    public ?string $versionId = null;

    /** @var array<string, string> пользовательские метаданные (x-amz-meta-*) */
    public array $metadata = [];

    /** @var list<BucketTagData>|null null — теги не поддерживаются или недоступны */
    public ?array $tags = null;
}
