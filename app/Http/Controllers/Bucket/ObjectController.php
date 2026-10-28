<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bucket;

use App\Domain\DTO\Bucket\BucketData;
use App\Domain\DTO\Operation\OperationData;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Object\ObjectAclReadRequest;
use App\Http\Requests\Object\ObjectListRequest;
use App\Http\Requests\Object\ObjectProtectionReadRequest;
use App\Http\Requests\Object\ObjectReadRequest;
use App\Http\Requests\Object\ObjectShareRequest;
use App\Http\Requests\Object\ObjectVersionsRequest;
use App\Http\Requests\Object\UploadListRequest;
use App\Models\Storage;
use App\Services\Bucket\BucketService;
use App\Services\Object\ObjectBrowserService;
use App\Services\Object\ObjectUploadService;
use App\Services\Operation\OperationService;
use App\Services\Storage\StorageCapabilities;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Просмотр объектов: страница обозревателя, подгрузка страниц, свойства, скачивание и временные ссылки.
 */
final class ObjectController extends Controller
{
    private const array FOLDER_OPERATIONS = [OperationType::FolderDelete, OperationType::FolderCopy, OperationType::FolderMove];

    public function __construct(
        private readonly ObjectBrowserService $browser,
        private readonly StorageService $storages,
        private readonly BucketService $buckets,
        private readonly OperationService $operations,
        private readonly StorageCapabilities $capabilities,
    ) {}

    public function index(ObjectListRequest $request, Storage $storage, string $bucket): Response
    {
        $actor = $request->authUser();
        $can = fn (StoragePermission $permission): bool => $actor->can('use', [$storage, $permission]);
        $has = fn (StorageCapability $capability): bool => $this->capabilities->has($storage, $capability);

        return Inertia::render('objects/Index', [
            'storage' => $this->storages->get($actor->id, $storage->id),
            'bucket' => $bucket,
            'prefix' => $request->prefix(),
            'search' => $request->search(),
            'listing' => fn () => $this->browser->list($storage->id, $bucket, $request->prefix(), $request->search()),
            'operations' => fn () => $this->folderOperations($storage->id, $bucket),
            // Бакеты для копирования и перемещения — только когда открывают диалог
            'buckets' => Inertia::optional(fn () => array_map(
                fn (BucketData $item) => $item->name,
                $this->buckets->list($storage->id)->buckets,
            )),
            'partSize' => ObjectUploadService::PART_SIZE,
            'maxLinkSeconds' => ObjectBrowserService::MAX_LINK_SECONDS,
            'can' => [
                'put' => $can(StoragePermission::PutObjects),
                'delete' => $can(StoragePermission::DeleteObjects),
                'share' => $can(StoragePermission::SharePresignedLinks),
                'manageAcl' => $can(StoragePermission::ManageAcl),
                'manageObjectLock' => $can(StoragePermission::ManageObjectLock),
                'bucketSettings' => $can(StoragePermission::ViewBuckets),
            ],
            'capabilities' => [
                'versioning' => $has(StorageCapability::Versioning),
                'tagging' => $has(StorageCapability::Tagging),
                'acl' => $has(StorageCapability::Acl),
                'objectLock' => $has(StorageCapability::ObjectLock),
                'uploads' => $has(StorageCapability::MultipartUploads),
            ],
        ]);
    }

    public function list(ObjectListRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->list($storage->id, $bucket, $request->prefix(), $request->search(), $request->token()));
    }

    public function details(ObjectReadRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->details($storage->id, $bucket, $request->reference()));
    }

    public function versions(ObjectVersionsRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->versions($storage->id, $bucket, $request->objectKey()));
    }

    public function acl(ObjectAclReadRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->acl($storage->id, $bucket, $request->objectKey()));
    }

    public function protection(ObjectProtectionReadRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->protection($storage->id, $bucket, $request->reference()));
    }

    public function uploads(UploadListRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json($this->browser->uploads($storage->id, $bucket));
    }

    /**
     * Скачивание — перенаправление на подписанную ссылку: файл идёт из хранилища напрямую, минуя сервер.
     */
    public function download(ObjectReadRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        return redirect()->away($this->browser->downloadUrl($storage->id, $bucket, $request->reference()));
    }

    public function share(ObjectShareRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $seconds = (int) $request->validated('expires');

        return response()->json([
            'url' => $this->browser->shareLink($storage->id, $bucket, $request->reference(), $seconds, $request->boolean('attachment')),
            'expiresAt' => now()->addSeconds($seconds)->toIso8601String(),
        ]);
    }

    /**
     * Операции над «папками» этого бакета: идущие и недавно упавшие.
     *
     * @return list<OperationData>
     */
    private function folderOperations(int $storageId, string $bucket): array
    {
        $relevant = fn (OperationData $operation): bool => $operation->bucket === $bucket
            && in_array($operation->type, self::FOLDER_OPERATIONS, true);

        return array_values(array_filter([
            ...$this->operations->activeFor($storageId),
            ...array_filter(
                $this->operations->recentFinished($storageId),
                fn (OperationData $operation) => $operation->status === OperationStatus::Failed,
            ),
        ], $relevant));
    }
}
