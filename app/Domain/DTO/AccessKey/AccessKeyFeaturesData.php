<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Что умеет управление ключами у провайдера.
 */
final class AccessKeyFeaturesData extends DTO
{
    /** Ключи принадлежат пользователям, и их можно выбирать */
    public bool $users = false;

    /** Ключ можно временно отключить */
    public bool $status = false;

    public bool $expiration = false;

    public bool $policy = false;

    public bool $name = false;

    public bool $description = false;
}
