<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Managers;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Exceptions\DomainException;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use App\Services\AccessKey\Support\RsaPss;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Статические ключи сервисного аккаунта Yandex Cloud. Доступ — по авторизованному ключу сервисного аккаунта:
 * из него подписывается JWT (PS256) и обменивается на IAM-токен.
 */
final readonly class YandexKeyManager implements AccessKeyManager
{
    private const string IAM = 'https://iam.api.cloud.yandex.net';

    /**
     * @param  array{id: string, service_account_id: string, private_key: string}  $authorizedKey
     */
    public function __construct(
        private Http $http,
        private Cache $cache,
        private array $authorizedKey,
        private string $serviceAccountId,
    ) {}

    public static function fields(): array
    {
        return [
            [
                'name' => 'authorized_key',
                'label' => __('Authorized key of a service account (JSON)'),
                'secret' => true,
                'multiline' => true,
                'required' => true,
                'hint' => __('Created with “yc iam key create”. The account needs the iam.serviceAccounts.accessKeyAdmin role on the service account whose keys are managed.'),
            ],
            [
                'name' => 'service_account_id',
                'label' => __('Service account whose keys are managed'),
                'hint' => __('Empty — the account of the authorized key.'),
            ],
        ];
    }

    public static function features(): AccessKeyFeaturesData
    {
        return new AccessKeyFeaturesData(['description' => true]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{id: string, service_account_id: string, private_key: string}
     */
    public static function parseKey(array $credentials): array
    {
        $key = json_decode((string) ($credentials['authorized_key'] ?? ''), true);

        if (! is_array($key) || ! isset($key['id'], $key['service_account_id'], $key['private_key'])) {
            throw new DomainException(__('The authorized key must be the JSON file created by “yc iam key create”.'));
        }

        return [
            'id' => (string) $key['id'],
            'service_account_id' => (string) $key['service_account_id'],
            'private_key' => (string) $key['private_key'],
        ];
    }

    public function users(): array
    {
        return [];
    }

    public function list(?string $user): array
    {
        $keys = [];
        $pageToken = null;

        do {
            $page = $this->api()->get('/iam/aws-compatibility/v1/accessKeys', array_filter([
                'serviceAccountId' => $this->serviceAccountId,
                'pageSize' => 100,
                'pageToken' => $pageToken,
            ]))->throw()->json();

            foreach ((array) ($page['accessKeys'] ?? []) as $key) {
                $keys[] = new AccessKeyData([
                    'id' => (string) $key['id'],
                    'accessKeyId' => (string) $key['keyId'],
                    'description' => ($key['description'] ?? '') !== '' ? (string) $key['description'] : null,
                    'user' => (string) ($key['serviceAccountId'] ?? $this->serviceAccountId),
                    'createdAt' => isset($key['createdAt']) ? CarbonImmutable::parse((string) $key['createdAt']) : null,
                ]);
            }

            $pageToken = ($page['nextPageToken'] ?? '') !== '' ? (string) $page['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $keys;
    }

    public function create(AccessKeyCreateData $data): AccessKeySecretData
    {
        $response = $this->api()->post('/iam/aws-compatibility/v1/accessKeys', array_filter([
            'serviceAccountId' => $this->serviceAccountId,
            'description' => $data->description,
        ]))->throw()->json();

        return new AccessKeySecretData([
            'accessKeyId' => (string) $response['accessKey']['keyId'],
            'secretAccessKey' => (string) $response['secret'],
        ]);
    }

    public function setStatus(string $id, ?string $user, bool $active): void
    {
        throw new DomainException(__('This provider cannot disable keys.'));
    }

    public function delete(string $id, ?string $user): void
    {
        $this->api()->delete('/iam/aws-compatibility/v1/accessKeys/'.rawurlencode($id))->throw();
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(self::IAM)->acceptJson()->timeout(20)->withToken($this->token());
    }

    /**
     * IAM-токен живёт до 12 часов; держим его в кэше час.
     */
    private function token(): string
    {
        return (string) $this->cache->remember('yandex-iam-token:'.hash('sha256', $this->authorizedKey['id']), 3600, function (): string {
            $now = time();
            $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

            $header = $encode((string) json_encode(['typ' => 'JWT', 'alg' => 'PS256', 'kid' => $this->authorizedKey['id']]));
            $payload = $encode((string) json_encode([
                'iss' => $this->authorizedKey['service_account_id'],
                'aud' => self::IAM.'/iam/v1/tokens',
                'iat' => $now,
                'exp' => $now + 600,
            ]));
            $jwt = $header.'.'.$payload.'.'.$encode(RsaPss::sign($header.'.'.$payload, $this->authorizedKey['private_key']));

            return (string) $this->http->baseUrl(self::IAM)->acceptJson()->timeout(20)
                ->post('/iam/v1/tokens', ['jwt' => $jwt])->throw()->json('iamToken');
        });
    }
}
