<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\AccessKey\AccessKeyStatus;
use Carbon\CarbonImmutable;

/**
 * Ключ доступа к S3 у провайдера. Секрет после создания не показывается.
 */
final class AccessKeyData extends DTO
{
    /** Идентификатор для операций у провайдера (у Yandex и Cloudflare отличается от access key ID) */
    public string $id;

    public string $accessKeyId;

    public ?string $name = null;

    public ?string $description = null;

    /** Владелец ключа у провайдера (пользователь IAM, пользователь MinIO) */
    public ?string $user = null;

    public AccessKeyStatus $status = AccessKeyStatus::Active;

    public ?CarbonImmutable $createdAt = null;

    public ?CarbonImmutable $expiresAt = null;
}
