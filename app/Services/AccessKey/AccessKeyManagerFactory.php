<?php

declare(strict_types=1);

namespace App\Services\AccessKey;

use App\Domain\Enum\Storage\StorageProvider;
use App\Exceptions\DomainException;
use App\Models\Storage;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use App\Services\AccessKey\Managers\AwsIamKeyManager;
use App\Services\AccessKey\Managers\CloudflareR2KeyManager;
use App\Services\AccessKey\Managers\KeystoneKeyManager;
use App\Services\AccessKey\Managers\MinioKeyManager;
use App\Services\AccessKey\Managers\YandexKeyManager;
use App\Services\AccessKey\Support\IamClientFactory;
use App\Services\AccessKey\Support\McRunner;
use App\Services\Storage\EndpointGuard;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;

/**
 * Менеджер ключей для хранилища: по провайдеру и сохранённым учётным данным управления.
 */
final readonly class AccessKeyManagerFactory
{
    public function __construct(
        private StorageRepositoryInterface $storages,
        private EndpointGuard $guard,
        private McRunner $mc,
        private IamClientFactory $iam,
        private Http $http,
        private Cache $cache,
    ) {}

    /**
     * @return class-string<AccessKeyManager>|null null — провайдер не поддерживается
     */
    public static function managerClass(StorageProvider $provider): ?string
    {
        return match ($provider) {
            StorageProvider::Aws => AwsIamKeyManager::class,
            StorageProvider::Minio => MinioKeyManager::class,
            StorageProvider::Yandex => YandexKeyManager::class,
            StorageProvider::Selectel, StorageProvider::VkCloud => KeystoneKeyManager::class,
            StorageProvider::CloudflareR2 => CloudflareR2KeyManager::class,
            StorageProvider::Generic => null,
        };
    }

    /**
     * Заполнены ли обязательные поля.
     */
    public static function isConfigured(Storage $storage): bool
    {
        $class = self::managerClass($storage->provider);

        if ($class === null) {
            return false;
        }

        $credentials = $storage->admin_credentials ?? [];

        foreach ($class::fields() as $field) {
            if (($field['required'] ?? false) && trim((string) ($credentials[$field['name']] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    public function forStorage(int $storageId): AccessKeyManager
    {
        $storage = $this->storages->findOrFail($storageId);

        if (self::managerClass($storage->provider) === null) {
            throw new DomainException(__('Key management is not available for this provider.'));
        }

        if (! self::isConfigured($storage)) {
            throw new DomainException(__('Key management is not configured for this storage.'));
        }

        $credentials = $storage->admin_credentials ?? [];
        $value = fn (string $name): string => trim((string) ($credentials[$name] ?? ''));

        // Пустые ключи управления — используем ключи самого хранилища
        $accessKey = $value('access_key_id') !== '' ? $value('access_key_id') : (string) $storage->access_key_id;
        $secretKey = $value('secret_access_key') !== '' ? $value('secret_access_key') : (string) $storage->secret_access_key;

        return match ($storage->provider) {
            StorageProvider::Aws => new AwsIamKeyManager($this->iam->make($accessKey, $secretKey)),
            StorageProvider::Minio => $this->minio($storage, $accessKey, $secretKey, $value('user')),
            StorageProvider::Yandex => $this->yandex($credentials, $value('service_account_id')),
            StorageProvider::Selectel, StorageProvider::VkCloud => $this->keystone($credentials),
            StorageProvider::CloudflareR2 => new CloudflareR2KeyManager($this->http, $value('account_id'), $value('api_token')),
            StorageProvider::Generic => throw new DomainException(__('Key management is not available for this provider.')),
        };
    }

    private function minio(Storage $storage, string $accessKey, string $secretKey, string $user): MinioKeyManager
    {
        $this->guard->assertAllowed($storage->endpoint);

        return new MinioKeyManager(
            mc: $this->mc,
            endpoint: (string) $storage->endpoint,
            accessKeyId: $accessKey,
            secretAccessKey: $secretKey,
            verifySsl: $storage->verify_ssl,
            defaultUser: $user !== '' ? $user : $accessKey,
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function yandex(array $credentials, string $serviceAccountId): YandexKeyManager
    {
        $key = YandexKeyManager::parseKey($credentials);

        return new YandexKeyManager($this->http, $this->cache, $key, $serviceAccountId !== '' ? $serviceAccountId : $key['service_account_id']);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function keystone(array $credentials): KeystoneKeyManager
    {
        $authUrl = (string) $credentials['auth_url'];
        $this->guard->assertAllowed($authUrl);

        return new KeystoneKeyManager(
            http: $this->http,
            authUrl: $authUrl,
            username: (string) $credentials['username'],
            password: (string) $credentials['password'],
            domain: (string) $credentials['domain'],
            projectId: (string) $credentials['project_id'],
        );
    }
}
