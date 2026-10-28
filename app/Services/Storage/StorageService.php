<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\Storage\ConnectionTestResultData;
use App\Domain\DTO\Storage\ProviderPresetData;
use App\Domain\DTO\Storage\StorageConnectionData;
use App\Domain\DTO\Storage\StorageData;
use App\Domain\DTO\Storage\StorageDetailsData;
use App\Domain\DTO\Storage\StorageSaveData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Exceptions\DomainException;
use App\Models\Storage;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\Permission\StoragePermissionResolver;
use Illuminate\Support\Facades\DB;

final readonly class StorageService
{
    public function __construct(
        private StorageRepositoryInterface $storages,
        private UserRepositoryInterface $users,
        private StoragePermissionResolver $permissions,
        private StorageMapper $mapper,
        private StorageConnectionTester $tester,
        private EndpointGuard $guard,
        private AuditService $audit,
    ) {}

    /**
     * Хранилища, видимые пользователю: все — тем, кто управляет хранилищами или выдаёт на них права,
     * остальным — те, на которые у них есть права.
     *
     * @return list<StorageData>
     */
    public function listFor(int $actorId): array
    {
        $actor = $this->users->findOrFail($actorId);
        $masks = $this->permissions->masks($actor);

        $seesAll = $actor->is_superadmin
            || $actor->permissions->has(SystemPermission::ManageStorages)
            || $actor->permissions->has(SystemPermission::GrantStoragePermissions);

        $storages = $seesAll ? $this->storages->allOrdered() : $this->storages->accessibleBy($actorId);

        return array_values($storages->map(fn (Storage $storage) => $this->mapper->toData(
            $storage,
            $actor->is_superadmin
                ? PermissionMask::all(StoragePermission::class)
                : ($masks[$storage->id] ?? PermissionMask::none(StoragePermission::class)),
        ))->all());
    }

    public function get(int $actorId, int $id): StorageData
    {
        $actor = $this->users->findOrFail($actorId);

        return $this->mapper->toData($this->storages->findOrFail($id), $this->permissions->mask($actor, $id));
    }

    public function details(int $id): StorageDetailsData
    {
        return $this->mapper->toDetails($this->storages->findOrFail($id));
    }

    /**
     * Параметры подключения с расшифрованными ключами — только для работы с хранилищем внутри сервисов.
     */
    public function connection(int $id): StorageConnectionData
    {
        return $this->mapper->toConnection($this->storages->findOrFail($id));
    }

    /**
     * @return list<ProviderPresetData>
     */
    public function presets(): array
    {
        return array_map($this->mapper->toPreset(...), StorageProvider::cases());
    }

    public function create(int $actorId, StorageSaveData $data): StorageDetailsData
    {
        if (! $data->hasNewCredentials()) {
            throw new DomainException(__('Access key and secret key are required.'));
        }

        $this->ensureNameAvailable($data->name);
        $this->guard->assertAllowed($data->endpoint);

        return DB::transaction(function () use ($actorId, $data): StorageDetailsData {
            $storage = $this->storages->create([
                ...$this->connectionAttributes($data),
                'access_key_id' => $data->accessKeyId,
                'secret_access_key' => $data->secretAccessKey,
                'created_by' => $actorId,
            ]);

            $this->record(AuditAction::StorageCreated, $storage, AuditDetails::changes([], $this->auditedAttributes($storage)));

            return $this->mapper->toDetails($storage);
        });
    }

    public function update(int $id, StorageSaveData $data): StorageDetailsData
    {
        $this->ensureNameAvailable($data->name, $id);
        $this->guard->assertAllowed($data->endpoint);

        return DB::transaction(function () use ($id, $data): StorageDetailsData {
            $before = $this->storages->findOrFail($id);
            $beforeAttributes = $this->auditedAttributes($before);

            $attributes = $this->connectionAttributes($data);

            if ($data->hasNewCredentials()) {
                $attributes['access_key_id'] = $data->accessKeyId;
                $attributes['secret_access_key'] = $data->secretAccessKey;
            }

            $storage = $this->storages->update($id, $attributes);

            $details = AuditDetails::changes($beforeAttributes, $this->auditedAttributes($storage));

            if ($data->hasNewCredentials()) {
                $details += AuditDetails::flags(['credentialsChanged' => true]);
            }

            if ($details !== []) {
                $this->record(AuditAction::StorageUpdated, $storage, $details);
            }

            return $this->mapper->toDetails($storage);
        });
    }

    /**
     * Удаляет учётную запись вместе с ключами и выданными правами. Само хранилище у провайдера не трогается.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $storage = $this->storages->findOrFail($id);
            $this->storages->delete($id);
            $this->record(AuditAction::StorageDeleted, $storage);
        });
    }

    /**
     * Проверка параметров из формы. При изменении хранилища пустые ключи берутся из сохранённых.
     */
    public function test(StorageSaveData $data, ?int $existingId = null): ConnectionTestResultData
    {
        $accessKeyId = $data->accessKeyId;
        $secret = $data->secretAccessKey;

        if (! $data->hasNewCredentials() && $existingId !== null) {
            $stored = $this->storages->findOrFail($existingId);
            $accessKeyId = $stored->access_key_id;
            $secret = $stored->secret_access_key;
        }

        if ($accessKeyId === null || $secret === null) {
            return new ConnectionTestResultData(['ok' => false, 'message' => __('Access key and secret key are required.')]);
        }

        return $this->tester->test(new StorageConnectionData([
            'provider' => $data->provider,
            'endpoint' => $data->endpoint,
            'region' => $data->region,
            'usePathStyle' => $data->usePathStyle,
            'verifySsl' => $data->verifySsl,
            'accessKeyId' => $accessKeyId,
            'secretAccessKey' => $secret,
            'buckets' => $data->buckets,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionAttributes(StorageSaveData $data): array
    {
        return [
            'name' => $data->name,
            'provider' => $data->provider,
            'endpoint' => $data->endpoint,
            'region' => $data->region,
            'use_path_style' => $data->usePathStyle,
            'verify_ssl' => $data->verifySsl,
            'buckets' => $data->buckets === [] ? null : $data->buckets,
        ];
    }

    /**
     * Поля для журнала: без ключей.
     *
     * @return array<string, mixed>
     */
    private function auditedAttributes(Storage $storage): array
    {
        return [
            'name' => $storage->name,
            'provider' => $storage->provider->label(),
            'endpoint' => $storage->endpoint,
            'region' => $storage->region,
            'use_path_style' => $storage->use_path_style,
            'verify_ssl' => $storage->verify_ssl,
            'buckets' => $storage->buckets,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(AuditAction $action, Storage $storage, array $details = []): void
    {
        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $storage->id,
            'subjectLabel' => $storage->name,
            'details' => $details,
        ]));
    }

    private function ensureNameAvailable(string $name, ?int $exceptId = null): void
    {
        if ($this->storages->nameTaken($name, $exceptId)) {
            throw new DomainException(__('A storage with this name already exists.'));
        }
    }
}
