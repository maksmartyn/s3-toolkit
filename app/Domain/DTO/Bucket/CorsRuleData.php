<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Правило CORS. В S3 API поля называются AllowedOrigins, AllowedMethods и т.д.
 */
final class CorsRuleData extends DTO
{
    public ?string $id = null;

    /** @var list<string> */
    public array $allowedOrigins = [];

    /** @var list<string> GET, PUT, POST, DELETE, HEAD */
    public array $allowedMethods = [];

    /** @var list<string> */
    public array $allowedHeaders = [];

    /** @var list<string> */
    public array $exposeHeaders = [];

    public ?int $maxAgeSeconds = null;
}
