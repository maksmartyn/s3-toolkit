<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;
use Carbon\CarbonImmutable;

/**
 * Параметры нового ключа. Что из этого учитывается, зависит от провайдера (AccessKeyFeaturesData).
 */
final class AccessKeyCreateData extends DTO
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $user = null;

    public ?CarbonImmutable $expiresAt = null;

    /** Политика, ограничивающая ключ (JSON), — только MinIO */
    public ?string $policy = null;
}
