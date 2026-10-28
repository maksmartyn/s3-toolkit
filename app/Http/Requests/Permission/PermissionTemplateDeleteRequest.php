<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

final class PermissionTemplateDeleteRequest extends PermissionTemplateActionRequest
{
    protected function ability(): string
    {
        return 'delete';
    }
}
