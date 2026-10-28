<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use App\Domain\Enum\Permission\StoragePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessKey\AccessKeyDeleteRequest;
use App\Http\Requests\AccessKey\AccessKeyIndexRequest;
use App\Http\Requests\AccessKey\AccessKeyStatusRequest;
use App\Http\Requests\AccessKey\AccessKeyStoreRequest;
use App\Http\Requests\AccessKey\KeyManagementCredentialsRequest;
use App\Models\Storage;
use App\Services\AccessKey\AccessKeyService;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class AccessKeyController extends Controller
{
    public function __construct(
        private readonly AccessKeyService $keys,
        private readonly StorageService $storages,
    ) {}

    public function index(AccessKeyIndexRequest $request, Storage $storage): Response
    {
        $actor = $request->authUser();
        $canManage = $actor->can('use', [$storage, StoragePermission::ManageAccessKeys]);
        $canConfigure = $actor->can('update', $storage);
        $settings = $this->keys->settings($storage->id, $canConfigure);
        $user = $request->keyUser();

        return Inertia::render('access-keys/Index', [
            'storage' => $this->storages->get($actor->id, $storage->id),
            'settings' => $settings,
            // Ключи запрашиваются у провайдера — грузим после открытия страницы
            'overview' => $canManage && $settings->configured
                ? Inertia::defer(fn () => $this->keys->overview($storage->id, $user))
                : null,
            'can' => ['manage' => $canManage, 'configure' => $canConfigure],
        ]);
    }

    public function store(AccessKeyStoreRequest $request, Storage $storage): JsonResponse
    {
        return response()->json($this->keys->create($storage->id, $request->toData()));
    }

    public function status(AccessKeyStatusRequest $request, Storage $storage): JsonResponse
    {
        $active = $request->boolean('active');
        $this->keys->setStatus($storage->id, (string) $request->validated('id'), $request->keyUser(), $active);

        return response()->json(['message' => $active ? __('Key enabled.') : __('Key disabled.')]);
    }

    public function destroy(AccessKeyDeleteRequest $request, Storage $storage): JsonResponse
    {
        $this->keys->delete($storage->id, (string) $request->validated('id'), $request->keyUser());

        return response()->json(['message' => __('Key deleted.')]);
    }

    public function credentials(KeyManagementCredentialsRequest $request, Storage $storage): RedirectResponse
    {
        $this->keys->saveCredentials($storage->id, $request->credentials());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Key management settings saved.')]);

        return back();
    }
}
