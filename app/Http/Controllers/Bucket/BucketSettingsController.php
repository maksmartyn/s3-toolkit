<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bucket;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bucket\BucketAclRequest;
use App\Http\Requests\Bucket\BucketCorsRequest;
use App\Http\Requests\Bucket\BucketLifecycleRequest;
use App\Http\Requests\Bucket\BucketObjectLockRequest;
use App\Http\Requests\Bucket\BucketPolicyRequest;
use App\Http\Requests\Bucket\BucketShowRequest;
use App\Http\Requests\Bucket\BucketTagsRequest;
use App\Http\Requests\Bucket\BucketVersioningRequest;
use App\Http\Requests\Bucket\BucketWebsiteRequest;
use App\Models\Storage;
use App\Services\Bucket\BucketService;
use App\Services\Bucket\BucketSettingsService;
use App\Services\Storage\StorageCapabilities;
use App\Services\Storage\StorageService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BucketSettingsController extends Controller
{
    public function __construct(
        private readonly BucketSettingsService $settings,
        private readonly BucketService $buckets,
        private readonly StorageService $storages,
        private readonly StorageCapabilities $capabilities,
    ) {}

    public function show(BucketShowRequest $request, Storage $storage, string $bucket): Response
    {
        $actor = $request->authUser();
        $has = fn (StorageCapability $capability): bool => $this->capabilities->has($storage, $capability);
        $can = fn (StoragePermission $permission): bool => $actor->can('use', [$storage, $permission]);

        // Каждый раздел — отдельная группа отложенной загрузки: медленный или упавший раздел не задерживает остальные
        $section = fn (StorageCapability $capability, string $group, Closure $load) => $has($capability)
            ? Inertia::defer($load, $group)
            : null;

        return Inertia::render('buckets/Show', [
            'storage' => $this->storages->get($actor->id, $storage->id),
            'bucket' => $bucket,
            'stats' => $this->buckets->stats($storage->id, $bucket),
            'versioning' => $section(StorageCapability::Versioning, 'versioning', fn () => $this->settings->versioning($storage->id, $bucket)),
            'tags' => $section(StorageCapability::Tagging, 'tags', fn () => $this->settings->tags($storage->id, $bucket)),
            'policy' => $section(StorageCapability::BucketPolicy, 'policy', fn () => $this->settings->policy($storage->id, $bucket)),
            'cors' => $section(StorageCapability::Cors, 'cors', fn () => $this->settings->cors($storage->id, $bucket)),
            'lifecycle' => $section(StorageCapability::Lifecycle, 'lifecycle', fn () => $this->settings->lifecycle($storage->id, $bucket)),
            'acl' => $section(StorageCapability::Acl, 'acl', fn () => $this->settings->acl($storage->id, $bucket)),
            'website' => $section(StorageCapability::Website, 'website', fn () => $this->settings->website($storage->id, $bucket)),
            'objectLock' => $section(StorageCapability::ObjectLock, 'objectLock', fn () => $this->settings->objectLock($storage->id, $bucket)),
            'can' => [
                'manageVersioning' => $can(StoragePermission::ManageVersioning),
                'manageTags' => $can(StoragePermission::ManageBucketTags),
                'managePolicy' => $can(StoragePermission::ManageBucketPolicy),
                'manageCors' => $can(StoragePermission::ManageCors),
                'manageLifecycle' => $can(StoragePermission::ManageLifecycle),
                'manageAcl' => $can(StoragePermission::ManageAcl),
                'manageWebsite' => $can(StoragePermission::ManageWebsite),
                'manageObjectLock' => $can(StoragePermission::ManageObjectLock),
            ],
        ]);
    }

    public function versioning(BucketVersioningRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->settings->setVersioning($storage->id, $bucket, $request->status());

        return $this->saved(__('Versioning updated.'));
    }

    public function tags(BucketTagsRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->settings->setTags($storage->id, $bucket, $request->toData());

        return $this->saved(__('Tags updated.'));
    }

    public function policy(BucketPolicyRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $policy = $request->policy();
        $this->settings->setPolicy($storage->id, $bucket, $policy);

        return $this->saved($policy === null ? __('Policy removed.') : __('Policy saved.'));
    }

    public function cors(BucketCorsRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $rules = $request->toData();
        $this->settings->setCors($storage->id, $bucket, $rules);

        return $this->saved($rules === [] ? __('CORS removed.') : __('CORS saved.'));
    }

    public function lifecycle(BucketLifecycleRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $rules = $request->toRules();
        $this->settings->setLifecycle($storage->id, $bucket, $rules);

        return $this->saved($rules === [] ? __('Lifecycle rules removed.') : __('Lifecycle rules saved.'));
    }

    public function acl(BucketAclRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->settings->setAcl($storage->id, $bucket, $request->acl());

        return $this->saved(__('ACL updated.'));
    }

    public function website(BucketWebsiteRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $website = $request->toData();
        $this->settings->setWebsite($storage->id, $bucket, $website);

        return $this->saved($website === null ? __('Website hosting disabled.') : __('Website hosting saved.'));
    }

    public function objectLock(BucketObjectLockRequest $request, Storage $storage, string $bucket): RedirectResponse
    {
        $this->settings->setObjectLock($storage->id, $bucket, $request->toData());

        return $this->saved(__('Object Lock updated.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
