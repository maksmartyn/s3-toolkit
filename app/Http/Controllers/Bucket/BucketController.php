<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bucket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bucket\BucketDeleteRequest;
use App\Http\Requests\Bucket\BucketStatsRequest;
use App\Http\Requests\Bucket\BucketStoreRequest;
use App\Models\Storage;
use App\Services\Bucket\BucketService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class BucketController extends Controller
{
    public function __construct(private readonly BucketService $buckets) {}

    public function store(BucketStoreRequest $request, Storage $storage): RedirectResponse
    {
        $data = $request->toData();
        $this->buckets->create($storage->id, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bucket :name created.', ['name' => $data->name])]);

        return back();
    }

    public function destroy(BucketDeleteRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->buckets->requestDeletion($request->authUser()->id, $storage->id, $bucket);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Deletion of :name started.', ['name' => $bucket])]);

        return back();
    }

    public function stats(BucketStatsRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->buckets->requestStats($request->authUser()->id, $storage->id, $bucket);

        return back();
    }
}
