<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use App\Domain\Enum\Permission\PermissionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StorageCreateRequest;
use App\Http\Requests\Storage\StorageDeleteRequest;
use App\Http\Requests\Storage\StorageEditRequest;
use App\Http\Requests\Storage\StorageIndexRequest;
use App\Http\Requests\Storage\StorageShowRequest;
use App\Http\Requests\Storage\StorageStoreRequest;
use App\Http\Requests\Storage\StorageTestConnectionRequest;
use App\Http\Requests\Storage\StorageUpdateRequest;
use App\Models\Storage;
use App\Services\Permission\PermissionCatalog;
use App\Services\Permission\PermissionTemplateService;
use App\Services\Storage\StorageAccessService;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class StorageController extends Controller
{
    public function __construct(
        private readonly StorageService $storages,
        private readonly StorageAccessService $accesses,
        private readonly PermissionCatalog $catalog,
        private readonly PermissionTemplateService $templates,
    ) {}

    public function index(StorageIndexRequest $request): Response
    {
        $actor = $request->authUser();

        return Inertia::render('storages/Index', [
            'storages' => $this->storages->listFor($actor->id),
            'can' => ['create' => $actor->can('create', Storage::class)],
        ]);
    }

    public function create(StorageCreateRequest $request): Response
    {
        return Inertia::render('storages/Form', [
            'storage' => null,
            'providers' => $this->storages->presets(),
        ]);
    }

    public function store(StorageStoreRequest $request): RedirectResponse
    {
        $storage = $this->storages->create($request->authUser()->id, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage added.')]);

        return to_route('storages.show', $storage->id);
    }

    public function show(StorageShowRequest $request, Storage $storage): Response
    {
        $actor = $request->authUser();
        $canManageAccess = $actor->can('manageAccess', $storage);

        return Inertia::render('storages/Show', [
            'storage' => $this->storages->get($actor->id, $storage->id),
            'details' => $actor->can('viewConnection', $storage) ? $this->storages->details($storage->id) : null,
            'permissionOptions' => $this->catalog->options(PermissionScope::Storage),
            'accesses' => $canManageAccess ? $this->accesses->list($storage->id) : [],
            'candidates' => $canManageAccess ? $this->accesses->candidates($actor->id) : [],
            'permissionTemplates' => $canManageAccess ? $this->templates->list(PermissionScope::Storage) : [],
            'can' => [
                'update' => $actor->can('update', $storage),
                'delete' => $actor->can('delete', $storage),
                'manageAccess' => $canManageAccess,
            ],
        ]);
    }

    public function edit(StorageEditRequest $request, Storage $storage): Response
    {
        return Inertia::render('storages/Form', [
            'storage' => $this->storages->details($storage->id),
            'providers' => $this->storages->presets(),
        ]);
    }

    public function update(StorageUpdateRequest $request, Storage $storage): RedirectResponse
    {
        $this->storages->update($storage->id, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage updated.')]);

        return to_route('storages.show', $storage->id);
    }

    public function destroy(StorageDeleteRequest $request, Storage $storage): RedirectResponse
    {
        $this->storages->delete($storage->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storage deleted.')]);

        return to_route('storages.index');
    }

    public function testConnection(StorageTestConnectionRequest $request): JsonResponse
    {
        return response()->json($this->storages->test($request->toData(), $request->existing()?->id));
    }
}
