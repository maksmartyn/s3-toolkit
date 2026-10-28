<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Managers;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Domain\Enum\AccessKey\AccessKeyStatus;
use App\Exceptions\DomainException;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Ключи R2 — API-токены аккаунта Cloudflare с правами на R2: access key ID — это ID токена,
 * секрет — SHA-256 от значения токена.
 */
final readonly class CloudflareR2KeyManager implements AccessKeyManager
{
    private const string API = 'https://api.cloudflare.com/client/v4';

    /** Группа прав для чтения и записи объектов во всех бакетах аккаунта */
    private const string PERMISSION_GROUP = 'Workers R2 Storage Bucket Item Write';

    public function __construct(
        private Http $http,
        private string $accountId,
        private string $apiToken,
    ) {}

    public static function fields(): array
    {
        return [
            ['name' => 'account_id', 'label' => __('Cloudflare Account ID'), 'required' => true],
            [
                'name' => 'api_token',
                'label' => __('API token'),
                'secret' => true,
                'required' => true,
                'hint' => __('An account API token with the “Account API Tokens: Edit” permission.'),
            ],
        ];
    }

    public static function features(): AccessKeyFeaturesData
    {
        return new AccessKeyFeaturesData(['status' => true, 'expiration' => true, 'name' => true]);
    }

    public function users(): array
    {
        return [];
    }

    public function list(?string $user): array
    {
        $tokens = (array) $this->api()->get($this->path('/tokens'), ['per_page' => 100])->throw()->json('result');

        $isR2 = fn (array $token): bool => array_any(
            (array) ($token['policies'] ?? []),
            fn (array $policy) => array_any(
                (array) ($policy['permission_groups'] ?? []),
                fn (array $group) => str_contains((string) ($group['name'] ?? ''), 'R2'),
            ),
        );

        return array_values(array_map(fn (array $token) => new AccessKeyData([
            'id' => (string) $token['id'],
            'accessKeyId' => (string) $token['id'],
            'name' => isset($token['name']) ? (string) $token['name'] : null,
            'status' => match ($token['status'] ?? 'active') {
                'disabled' => AccessKeyStatus::Inactive,
                'expired' => AccessKeyStatus::Expired,
                default => AccessKeyStatus::Active,
            },
            'createdAt' => isset($token['issued_on']) ? CarbonImmutable::parse((string) $token['issued_on']) : null,
            'expiresAt' => isset($token['expires_on']) ? CarbonImmutable::parse((string) $token['expires_on']) : null,
        ]), array_filter($tokens, $isR2)));
    }

    public function create(AccessKeyCreateData $data): AccessKeySecretData
    {
        $groups = (array) $this->api()->get($this->path('/tokens/permission_groups'))->throw()->json('result');
        $group = array_find($groups, fn (array $item) => ($item['name'] ?? null) === self::PERMISSION_GROUP);

        if ($group === null) {
            throw new DomainException(__('The R2 permission group was not found in the Cloudflare account.'));
        }

        $token = (array) $this->api()->post($this->path('/tokens'), array_filter([
            'name' => $data->name ?? 'S3 Toolkit '.now()->format('Y-m-d H:i'),
            'policies' => [[
                'effect' => 'allow',
                'resources' => ['com.cloudflare.api.account.'.$this->accountId => '*'],
                'permission_groups' => [['id' => $group['id']]],
            ]],
            'expires_on' => $data->expiresAt?->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->throw()->json('result');

        return new AccessKeySecretData([
            'accessKeyId' => (string) $token['id'],
            'secretAccessKey' => hash('sha256', (string) $token['value']),
        ]);
    }

    public function setStatus(string $id, ?string $user, bool $active): void
    {
        $token = (array) $this->api()->get($this->path('/tokens/'.rawurlencode($id)))->throw()->json('result');

        $this->api()->put($this->path('/tokens/'.rawurlencode($id)), array_filter([
            'name' => $token['name'] ?? null,
            'policies' => $token['policies'] ?? [],
            'condition' => $token['condition'] ?? null,
            'expires_on' => $token['expires_on'] ?? null,
            'not_before' => $token['not_before'] ?? null,
            'status' => $active ? 'active' : 'disabled',
        ], fn (mixed $value) => $value !== null))->throw();
    }

    public function delete(string $id, ?string $user): void
    {
        $this->api()->delete($this->path('/tokens/'.rawurlencode($id)))->throw();
    }

    private function path(string $suffix): string
    {
        return '/accounts/'.rawurlencode($this->accountId).$suffix;
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(self::API)->acceptJson()->timeout(20)->withToken($this->apiToken);
    }
}
