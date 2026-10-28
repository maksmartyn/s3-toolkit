<?php

declare(strict_types=1);

namespace App\Services\AccessKey;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyOverviewData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Domain\DTO\AccessKey\AccessKeySettingsData;
use App\Domain\DTO\AccessKey\AdminCredentialFieldData;
use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\Enum\Audit\AuditAction;
use App\Exceptions\DomainException;
use App\Models\Storage;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use App\Services\AccessKey\Managers\YandexKeyManager;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\Storage\StorageErrorTranslator;
use Aws\Exception\AwsException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Ключи доступа к S3 у провайдера: просмотр, создание, отключение и удаление, а также учётные данные управления.
 * Секрет нового ключа возвращается один раз и нигде не сохраняется.
 */
final readonly class AccessKeyService
{
    public function __construct(
        private StorageRepositoryInterface $storages,
        private AccessKeyManagerFactory $managers,
        private StorageErrorTranslator $storageErrors,
        private AuditService $audit,
    ) {}

    /**
     * @param  bool  $withValues  показывать значения несекретных полей (только тем, кто может их менять)
     */
    public function settings(int $storageId, bool $withValues): AccessKeySettingsData
    {
        $storage = $this->storages->findOrFail($storageId);
        $class = AccessKeyManagerFactory::managerClass($storage->provider);

        if ($class === null) {
            return new AccessKeySettingsData;
        }

        $credentials = $storage->admin_credentials ?? [];

        return new AccessKeySettingsData([
            'supported' => true,
            'configured' => AccessKeyManagerFactory::isConfigured($storage),
            'features' => $class::features(),
            'fields' => $withValues ? array_map(fn (array $field) => new AdminCredentialFieldData([
                ...array_diff_key($field, ['resetsSecrets' => true]),
                'value' => ($field['secret'] ?? false) ? null : (isset($credentials[$field['name']]) ? (string) $credentials[$field['name']] : null),
                'isSet' => ($field['secret'] ?? false) && trim((string) ($credentials[$field['name']] ?? '')) !== '',
            ]), $class::fields()) : [],
        ]);
    }

    /**
     * Сохраняет учётные данные управления. Пустое секретное поле — оставить прежнее значение.
     *
     * @param  array<string, string|null>  $values
     */
    public function saveCredentials(int $storageId, array $values): void
    {
        $storage = $this->storages->findOrFail($storageId);
        $class = AccessKeyManagerFactory::managerClass($storage->provider)
            ?? throw new DomainException(__('Key management is not available for this provider.'));

        $before = $storage->admin_credentials ?? [];
        $after = [];
        $keptSecrets = false;

        foreach ($class::fields() as $field) {
            $value = trim((string) ($values[$field['name']] ?? ''));

            if ($value === '' && ($field['secret'] ?? false)) {
                $value = (string) ($before[$field['name']] ?? '');
                $keptSecrets = $keptSecrets || $value !== '';
            }

            if ($value !== '') {
                $after[$field['name']] = $value;
            }
        }

        if (isset($after['authorized_key'])) {
            YandexKeyManager::parseKey($after);
        }

        $changed = array_values(array_filter(
            array_map(fn (array $field) => $field['name'], $class::fields()),
            fn (string $name) => ($before[$name] ?? null) !== ($after[$name] ?? null),
        ));

        if ($changed === []) {
            return;
        }

        // Иначе сохранённый секрет можно было бы отправить на подменённый адрес
        foreach ($class::fields() as $field) {
            if (($field['resetsSecrets'] ?? false) && in_array($field['name'], $changed, true) && $keptSecrets) {
                throw new DomainException(__('After changing :field enter the secret fields again.', ['field' => $field['label']]));
            }
        }

        $this->storages->update($storageId, ['admin_credentials' => $after === [] ? null : $after]);

        $labels = array_column($class::fields(), 'label', 'name');

        $this->record(AuditAction::KeyManagementConfigured, $storage, AuditDetails::values([
            'fields' => implode(', ', array_map(fn (string $name) => $labels[$name] ?? $name, $changed)),
        ]));
    }

    public function overview(int $storageId, ?string $user): AccessKeyOverviewData
    {
        try {
            $manager = $this->managers->forStorage($storageId);
            $users = $manager::features()->users ? $manager->users() : [];

            return new AccessKeyOverviewData([
                'keys' => $manager->list($user),
                'users' => $users,
                'user' => $user,
            ]);
        } catch (DomainException $e) {
            return new AccessKeyOverviewData(['error' => $e->getMessage(), 'user' => $user]);
        } catch (Throwable $e) {
            return new AccessKeyOverviewData(['error' => $this->message($e), 'user' => $user]);
        }
    }

    public function create(int $storageId, AccessKeyCreateData $data): AccessKeySecretData
    {
        $secret = $this->call($storageId, fn (AccessKeyManager $manager) => $manager->create($data));

        $this->record(AuditAction::AccessKeyCreated, $this->storages->findOrFail($storageId), AuditDetails::values(array_filter([
            'access_key' => $secret->accessKeyId,
            'key_name' => $data->name ?? $data->description,
            'key_user' => $data->user,
            'expires' => $data->expiresAt?->utc()->toDateTimeString(),
        ])));

        return $secret;
    }

    public function setStatus(int $storageId, string $id, ?string $user, bool $active): void
    {
        $this->ensureNotServiceKey($storageId, $id);

        $this->call($storageId, fn (AccessKeyManager $manager) => $manager->setStatus($id, $user, $active));

        $this->record(AuditAction::AccessKeyStatusChanged, $this->storages->findOrFail($storageId), AuditDetails::values(array_filter([
            'access_key' => $id,
            'key_user' => $user,
            'key_status' => $active ? __('Active') : __('Disabled'),
        ])));
    }

    public function delete(int $storageId, string $id, ?string $user): void
    {
        $this->ensureNotServiceKey($storageId, $id);

        $this->call($storageId, fn (AccessKeyManager $manager) => $manager->delete($id, $user));

        $this->record(AuditAction::AccessKeyDeleted, $this->storages->findOrFail($storageId), AuditDetails::values(array_filter([
            'access_key' => $id,
            'key_user' => $user,
        ])));
    }

    /**
     * Ключами, которыми пользуется сам сервис (ключ хранилища и ключ управления), из интерфейса управлять нельзя:
     * их отключение сломало бы работу с хранилищем для всех.
     */
    private function ensureNotServiceKey(int $storageId, string $id): void
    {
        $storage = $this->storages->findOrFail($storageId);
        $protected = array_filter([
            (string) $storage->access_key_id,
            (string) ($storage->admin_credentials['access_key_id'] ?? ''),
        ]);

        $accessKeyId = $id;

        // У Yandex идентификатор для операций отличается от access key ID
        if (AccessKeyManagerFactory::managerClass($storage->provider) === YandexKeyManager::class) {
            $key = array_find($this->call($storageId, fn (AccessKeyManager $manager) => $manager->list(null)), fn ($key) => $key->id === $id);
            $accessKeyId = $key->accessKeyId ?? $id;
        }

        if (in_array($accessKeyId, $protected, true)) {
            throw new DomainException(__('This key is used by the service itself and cannot be changed here.'));
        }
    }

    /**
     * @template T
     *
     * @param  Closure(AccessKeyManager): T  $call
     * @return T
     */
    private function call(int $storageId, Closure $call): mixed
    {
        $manager = $this->managers->forStorage($storageId);

        try {
            return $call($manager);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException(__('The provider rejected the request: :error', ['error' => $this->message($e)]), previous: $e);
        }
    }

    private function message(Throwable $e): string
    {
        if ($e instanceof AwsException) {
            return $this->storageErrors->message($e);
        }

        if ($e instanceof ConnectionException) {
            return __('Could not connect to the provider API.');
        }

        if ($e instanceof RequestException) {
            $json = (array) $e->response->json();
            $message = $json['message'] ?? $json['error']['message'] ?? $json['errors'][0]['message'] ?? $json['error_description'] ?? null;

            return match (true) {
                is_string($message) && $message !== '' => $message.' (HTTP '.$e->response->status().')',
                in_array($e->response->status(), [401, 403], true) => __('Access denied. Check the key management credentials.'),
                default => 'HTTP '.$e->response->status(),
            };
        }

        return $e->getMessage() !== '' ? $e->getMessage() : __('Provider error.');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(AuditAction $action, Storage $storage, array $details): void
    {
        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $storage->id,
            'subjectLabel' => $storage->name,
            'details' => $details,
        ]));
    }
}
