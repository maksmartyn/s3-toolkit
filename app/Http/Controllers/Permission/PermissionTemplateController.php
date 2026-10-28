<?php

declare(strict_types=1);

namespace App\Http\Controllers\Permission;

use App\Domain\Enum\Permission\PermissionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\PermissionTemplateCreateRequest;
use App\Http\Requests\Permission\PermissionTemplateDeleteRequest;
use App\Http\Requests\Permission\PermissionTemplateEditRequest;
use App\Http\Requests\Permission\PermissionTemplateIndexRequest;
use App\Http\Requests\Permission\PermissionTemplateStoreRequest;
use App\Http\Requests\Permission\PermissionTemplateUpdateRequest;
use App\Models\PermissionTemplate;
use App\Services\Permission\PermissionCatalog;
use App\Services\Permission\PermissionTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PermissionTemplateController extends Controller
{
    public function __construct(
        private readonly PermissionTemplateService $templates,
        private readonly PermissionCatalog $catalog,
    ) {}

    public function index(PermissionTemplateIndexRequest $request): Response
    {
        return Inertia::render('permission-templates/Index', [
            'templates' => $this->templates->list(),
            'scopes' => $this->scopes(),
        ]);
    }

    public function create(PermissionTemplateCreateRequest $request): Response
    {
        $scope = $request->scope();

        return Inertia::render('permission-templates/Form', [
            'template' => null,
            'scope' => $this->scopeData($scope),
        ]);
    }

    public function store(PermissionTemplateStoreRequest $request): RedirectResponse
    {
        $this->templates->create($request->scope(), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return to_route('permission-templates.index');
    }

    public function edit(PermissionTemplateEditRequest $request, PermissionTemplate $template): Response
    {
        $data = $this->templates->get($template->id);

        return Inertia::render('permission-templates/Form', [
            'template' => $data,
            'scope' => $this->scopeData($data->scope),
        ]);
    }

    public function update(PermissionTemplateUpdateRequest $request, PermissionTemplate $template): RedirectResponse
    {
        $this->templates->update($template->id, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return to_route('permission-templates.index');
    }

    public function destroy(PermissionTemplateDeleteRequest $request, PermissionTemplate $template): RedirectResponse
    {
        $this->templates->delete($template->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return to_route('permission-templates.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scopes(): array
    {
        return array_map($this->scopeData(...), PermissionScope::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeData(PermissionScope $scope): array
    {
        return [
            'value' => $scope->value,
            'label' => $scope->label(),
            'options' => $this->catalog->options($scope),
        ];
    }
}
