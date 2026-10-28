<?php

declare(strict_types=1);

namespace App\Domain\DTO\Permission;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\ValueObject\Permission\PermissionMask;

/**
 * Создание и изменение шаблона. Вид прав (scope) задаётся при создании и потом не меняется.
 */
final class PermissionTemplateSaveData extends DTO
{
    public string $name;

    /** @var PermissionMask<BitPermission> */
    public PermissionMask $permissions;
}
