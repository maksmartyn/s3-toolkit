<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bucket;

use App\Domain\Enum\Operation\OperationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Object\FolderCreateRequest;
use App\Http\Requests\Object\FolderOperationRequest;
use App\Http\Requests\Object\ObjectAclRequest;
use App\Http\Requests\Object\ObjectDeleteRequest;
use App\Http\Requests\Object\ObjectLegalHoldRequest;
use App\Http\Requests\Object\ObjectMetadataRequest;
use App\Http\Requests\Object\ObjectRenameRequest;
use App\Http\Requests\Object\ObjectRestoreRequest;
use App\Http\Requests\Object\ObjectRetentionRequest;
use App\Http\Requests\Object\ObjectTagsRequest;
use App\Http\Requests\Object\ObjectTransferRequest;
use App\Http\Requests\Object\UploadAbortRequest;
use App\Models\Storage;
use App\Services\Object\ObjectManageService;
use Illuminate\Http\JsonResponse;

/**
 * Изменение объектов. Ответы — JSON: обозреватель обновляет список сам, не теряя подгруженные страницы.
 */
final class ObjectManageController extends Controller
{
    public function __construct(private readonly ObjectManageService $objects) {}

    public function createFolder(FolderCreateRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->createFolder($storage->id, $bucket, $request->folder());

        return $this->done(__('Folder created.'));
    }

    public function destroy(ObjectDeleteRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $objects = $request->objects();
        $this->objects->delete($storage->id, $bucket, $objects);

        return $this->done(trans_choice(':count object deleted.|:count objects deleted.', count($objects)));
    }

    public function transfer(ObjectTransferRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $move = $request->boolean('move');

        $this->objects->transfer(
            $storage->id,
            $bucket,
            $request->keys(),
            $request->prefix('source_prefix'),
            (string) $request->validated('target_bucket'),
            $request->prefix('target_prefix'),
            $move,
        );

        return $this->done($move ? __('Objects moved.') : __('Objects copied.'));
    }

    public function rename(ObjectRenameRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->rename($storage->id, $bucket, $request->objectKey(), (string) $request->validated('new_key'));

        return $this->done(__('Object renamed.'));
    }

    public function folderOperation(FolderOperationRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $type = $request->type();

        $operation = $this->objects->startFolderOperation(
            $request->authUser()->id,
            $storage->id,
            $bucket,
            $type,
            $request->prefix(),
            $type === OperationType::FolderDelete ? null : (string) $request->validated('target_bucket'),
            $type === OperationType::FolderDelete ? null : $request->prefix('target_prefix'),
        );

        return response()->json(['message' => __(':operation started.', ['operation' => $operation->typeLabel]), 'operation' => $operation]);
    }

    public function metadata(ObjectMetadataRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->updateMetadata($storage->id, $bucket, $request->objectKey(), $request->toData());

        return $this->done(__('Metadata saved.'));
    }

    public function tags(ObjectTagsRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->updateTags($storage->id, $bucket, $request->objectKey(), $request->tags());

        return $this->done(__('Tags updated.'));
    }

    public function acl(ObjectAclRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->setAcl($storage->id, $bucket, $request->objectKey(), $request->acl());

        return $this->done(__('ACL updated.'));
    }

    public function retention(ObjectRetentionRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->setRetention($storage->id, $bucket, $request->reference(), $request->mode(), $request->until());

        return $this->done(__('Retention updated.'));
    }

    public function legalHold(ObjectLegalHoldRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $on = $request->boolean('on');
        $this->objects->setLegalHold($storage->id, $bucket, $request->reference(), $on);

        return $this->done($on ? __('Legal hold enabled.') : __('Legal hold removed.'));
    }

    public function restore(ObjectRestoreRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->restoreVersion($storage->id, $bucket, $request->objectKey(), (string) $request->versionId());

        return $this->done(__('Version restored.'));
    }

    public function abortUploads(UploadAbortRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->objects->abortUploads($storage->id, $bucket, $request->uploads());

        return $this->done(__('Uploads aborted.'));
    }

    private function done(string $message): JsonResponse
    {
        return response()->json(['message' => $message]);
    }
}
