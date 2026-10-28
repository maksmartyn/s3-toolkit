<?php

namespace App\Http\Middleware;

use App\Models\AuditLogEntry;
use App\Models\PermissionTemplate;
use App\Models\Storage;
use App\Models\User;
use App\Repositories\Storage\StorageAccessRepositoryInterface;
use App\Services\User\UserMapper;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly UserMapper $users,
        private readonly StorageAccessRepositoryInterface $storageAccesses,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user instanceof User ? $this->users->toData($user) : null,
                'can' => [
                    'viewUsers' => $user instanceof User && $user->can('viewAny', User::class),
                    'viewPermissionTemplates' => $user instanceof User && $user->can('viewAny', PermissionTemplate::class),
                    'viewAuditLog' => $user instanceof User && $user->can('viewAny', AuditLogEntry::class),
                    'viewStorages' => fn (): bool => $user instanceof User && $this->seesStorages($user),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Пункт меню «Хранилища»: тем, кто управляет ими или выдаёт права, и тем, у кого есть доступ хотя бы к одному.
     */
    private function seesStorages(User $user): bool
    {
        return $user->can('create', Storage::class)
            || $user->can('manageAccess', new Storage)
            || $this->storageAccesses->userHasAnyAccess($user->id);
    }
}
