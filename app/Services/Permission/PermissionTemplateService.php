<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Domain\DTO\Permission\PermissionTemplateData;
use App\Domain\DTO\Permission\PermissionTemplateSaveData;
use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Exceptions\DomainException;
use App\Models\PermissionTemplate;
use App\Repositories\Permission\PermissionTemplateRepositoryInterface;
use InvalidArgumentException;

/**
 * Шаблоны — готовые наборы прав. При выдаче маска шаблона копируется пользователю,
 * поэтому изменение или удаление шаблона не меняет уже выданные права.
 */
final readonly class PermissionTemplateService
{
    public function __construct(private PermissionTemplateRepositoryInterface $templates) {}

    /**
     * @return list<PermissionTemplateData>
     */
    public function list(?PermissionScope $scope = null): array
    {
        return array_values($this->templates->allOrdered($scope)->map($this->toData(...))->all());
    }

    public function get(int $id): PermissionTemplateData
    {
        return $this->toData($this->templates->findOrFail($id));
    }

    public function create(PermissionScope $scope, PermissionTemplateSaveData $data): PermissionTemplateData
    {
        $this->ensureMaskMatches($scope, $data);
        $this->ensureNameAvailable($scope, $data->name);

        return $this->toData($this->templates->create([
            'name' => $data->name,
            'scope' => $scope,
            'permissions' => $data->permissions->value,
        ]));
    }

    public function update(int $id, PermissionTemplateSaveData $data): PermissionTemplateData
    {
        $template = $this->templates->findOrFail($id);

        $this->ensureMaskMatches($template->scope, $data);
        $this->ensureNameAvailable($template->scope, $data->name, $id);

        return $this->toData($this->templates->update($id, [
            'name' => $data->name,
            'permissions' => $data->permissions->value,
        ]));
    }

    public function delete(int $id): void
    {
        $this->templates->delete($id);
    }

    private function toData(PermissionTemplate $template): PermissionTemplateData
    {
        return new PermissionTemplateData([
            'id' => $template->id,
            'name' => $template->name,
            'scope' => $template->scope,
            'permissions' => PermissionMask::fromInt($template->scope->enum(), $template->permissions)->values(),
            'updatedAt' => $template->updated_at?->toImmutable(),
        ]);
    }

    private function ensureMaskMatches(PermissionScope $scope, PermissionTemplateSaveData $data): void
    {
        if ($data->permissions->enum !== $scope->enum()) {
            throw new InvalidArgumentException("Template of scope {$scope->value} expects {$scope->enum()} mask.");
        }
    }

    private function ensureNameAvailable(PermissionScope $scope, string $name, ?int $exceptId = null): void
    {
        if ($this->templates->nameTaken($scope, $name, $exceptId)) {
            throw new DomainException(__('A template with this name already exists.'));
        }
    }
}
