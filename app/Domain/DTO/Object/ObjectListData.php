<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Страница содержимого «папки». Ошибка хранилища возвращается текстом.
 */
final class ObjectListData extends DTO
{
    public string $prefix = '';

    /** @var list<string> полные префиксы подпапок */
    public array $folders = [];

    /** @var list<ObjectItemData> */
    public array $objects = [];

    /** Маркер следующей страницы */
    public ?string $next = null;

    public ?string $error = null;
}
