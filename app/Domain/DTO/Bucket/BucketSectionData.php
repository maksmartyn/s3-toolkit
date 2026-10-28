<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Один раздел настроек бакета: значение или ошибка хранилища. Разделы загружаются независимо,
 * чтобы ошибка одного (например, нет прав на чтение политики) не ломала остальные.
 */
final class BucketSectionData extends DTO
{
    public mixed $data = null;

    public ?string $error = null;
}
