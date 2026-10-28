<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Возможность управлять ключами для хранилища и форма учётных данных управления.
 */
final class AccessKeySettingsData extends DTO
{
    public bool $supported = false;

    public bool $configured = false;

    public ?AccessKeyFeaturesData $features = null;

    /** @var list<AdminCredentialFieldData> */
    public array $fields = [];
}
