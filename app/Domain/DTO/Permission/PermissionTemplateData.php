<?php

declare(strict_types=1);

namespace App\Domain\DTO\Permission;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Permission\PermissionScope;
use Carbon\CarbonImmutable;

final class PermissionTemplateData extends DTO
{
    public int $id;

    public string $name;

    public PermissionScope $scope;

    /** @var list<int> значения прав (номера битов) */
    public array $permissions;

    public ?CarbonImmutable $updatedAt;
}
