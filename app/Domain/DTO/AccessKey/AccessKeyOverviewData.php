<?php

declare(strict_types=1);

namespace App\Domain\DTO\AccessKey;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Ключи хранилища для страницы управления. Ошибка провайдера возвращается текстом.
 */
final class AccessKeyOverviewData extends DTO
{
    /** @var list<AccessKeyData> */
    public array $keys = [];

    /** @var list<string> пользователи, если ключи принадлежат пользователям */
    public array $users = [];

    /** Пользователь, чьи ключи показаны; null — ключи учётной записи управления */
    public ?string $user = null;

    public ?string $error = null;
}
