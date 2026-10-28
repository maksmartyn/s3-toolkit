<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bucket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Object\MultipartAbortRequest;
use App\Http\Requests\Object\MultipartCompleteRequest;
use App\Http\Requests\Object\MultipartPartRequest;
use App\Http\Requests\Object\MultipartStartRequest;
use App\Http\Requests\Object\ObjectUploadRequest;
use App\Models\Storage;
use App\Services\Object\ObjectUploadService;
use Illuminate\Http\JsonResponse;

/**
 * Загрузка через сервер: небольшие файлы целиком, крупные — частями (браузер присылает их по очереди).
 */
final class ObjectUploadController extends Controller
{
    public function __construct(private readonly ObjectUploadService $uploads) {}

    public function store(ObjectUploadRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->uploads->put($storage->id, $bucket, $request->objectKey(), $request->filePath(), $request->contentType());

        return response()->json(['key' => $request->objectKey()]);
    }

    public function start(MultipartStartRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $type = $request->validated('content_type');

        return response()->json([
            'uploadId' => $this->uploads->start($storage->id, $bucket, $request->objectKey(), is_string($type) && $type !== '' ? $type : null),
        ]);
    }

    public function part(MultipartPartRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        return response()->json(['etag' => $this->uploads->part(
            $storage->id,
            $bucket,
            $request->objectKey(),
            (string) $request->validated('upload_id'),
            (int) $request->validated('part_number'),
            $request->filePath(),
        )]);
    }

    public function complete(MultipartCompleteRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->uploads->complete($storage->id, $bucket, $request->objectKey(), (string) $request->validated('upload_id'), $request->parts());

        return response()->json(['key' => $request->objectKey()]);
    }

    public function abort(MultipartAbortRequest $request, Storage $storage, string $bucket): JsonResponse
    {
        $this->uploads->abort($storage->id, $bucket, $request->objectKey(), (string) $request->validated('upload_id'));

        return response()->json(['aborted' => true]);
    }
}
