<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StorageAccessUpdateRequest;
use App\Models\Storage;
use App\Services\Storage\StorageAccessService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class StorageAccessController extends Controller
{
    public function __construct(private readonly StorageAccessService $accesses) {}

    public function update(StorageAccessUpdateRequest $request, Storage $storage): RedirectResponse
    {
        $this->accesses->set($request->authUser()->id, $storage->id, $request->userId(), $request->toMask());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated.')]);

        return back();
    }
}
