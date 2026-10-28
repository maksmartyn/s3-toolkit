<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
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
use App\Services\Bucket\BucketService;
use App\Services\Operation\OperationService;
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
        private readonly BucketService $buckets,
        private readonly OperationService $operations,
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
            'capabilityOptions' => $this->capabilityOptions(),
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
        $canViewBuckets = $actor->can('use', [$storage, StoragePermission::ViewBuckets]);

        return Inertia::render('storages/Show', [
            // Список бакетов запрашивается у хранилища — грузим после открытия страницы
            'buckets' => $canViewBuckets ? Inertia::defer(fn () => $this->buckets->list($storage->id)) : null,
            'recentOperations' => $canViewBuckets ? Inertia::defer(fn () => $this->operations->recentFinished($storage->id, 5)) : [],
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
                'viewBuckets' => $canViewBuckets,
                'createBuckets' => $actor->can('use', [$storage, StoragePermission::CreateBuckets]),
                'deleteBuckets' => $actor->can('use', [$storage, StoragePermission::DeleteBuckets]),
                'viewObjects' => $actor->can('use', [$storage, StoragePermission::ViewObjects]),
            ],
        ]);
    }

    public function edit(StorageEditRequest $request, Storage $storage): Response
    {
        return Inertia::render('storages/Form', [
            'storage' => $this->storages->details($storage->id),
            'providers' => $this->storages->presets(),
            'capabilityOptions' => $this->capabilityOptions(),
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

    /**
     * @return list<array{value: string, label: string}>
     */
    private function capabilityOptions(): array
    {
        return array_map(
            fn (StorageCapability $capability) => ['value' => $capability->value, 'label' => $capability->label()],
            StorageCapability::cases(),
        );
    }
}
