<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

final class PermissionTemplateEditRequest extends PermissionTemplateActionRequest
{
    protected function ability(): string
    {
        return 'update';
    }
}
