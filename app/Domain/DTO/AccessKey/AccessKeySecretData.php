<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Созданный ключ с секретом: показывается один раз и нигде не сохраняется.
 */
final class AccessKeySecretData extends DTO
{
    public string $accessKeyId;

    public string $secretAccessKey;
}
