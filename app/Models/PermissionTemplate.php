<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enum\Permission\PermissionScope;
use Carbon\CarbonInterface;
use Database\Factories\PermissionTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Маска хранится числом: её тип зависит от scope, поэтому VO собирает маппер.
 *
 * @property int $id
 * @property string $name
 * @property PermissionScope $scope
 * @property int $permissions
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'scope', 'permissions'])]
class PermissionTemplate extends Model
{
    /** @use HasFactory<PermissionTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => PermissionScope::class,
            'permissions' => 'integer',
        ];
    }
}
