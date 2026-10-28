<?php

declare(strict_types=1);

namespace App\Domain\DTO\Permission;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Право для выбора в интерфейсе.
 */
final class PermissionOptionData extends DTO
{
    public int $value;

    public string $name;

    public string $label;
}
