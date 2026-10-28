<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Managers;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Exceptions\DomainException;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * S3-ключи OpenStack (EC2 credentials в Keystone) — так устроены Selectel и VK Cloud.
 * Ключи принадлежат пользователю, от имени которого выполняется вход, и привязаны к проекту.
 */
final class KeystoneKeyManager implements AccessKeyManager
{
    private ?string $token = null;

    private ?string $userId = null;

    public function __construct(
        private readonly Http $http,
        private readonly string $authUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $domain,
        private readonly string $projectId,
    ) {}

    public static function fields(): array
    {
        return [
            [
                'name' => 'auth_url',
                'label' => __('Keystone URL (Identity API v3)'),
                'required' => true,
                'placeholder' => 'https://cloud.api.selcloud.ru/identity/v3',
                'hint' => __('Selectel: https://cloud.api.selcloud.ru/identity/v3, VK Cloud: https://infra.mail.ru:35357/v3'),
            ],
            ['name' => 'username', 'label' => __('User name'), 'required' => true],
            ['name' => 'password', 'label' => __('Password'), 'secret' => true, 'required' => true],
            [
                'name' => 'domain',
                'label' => __('User domain'),
                'required' => true,
                'hint' => __('Selectel: account number, VK Cloud: users'),
            ],
            ['name' => 'project_id', 'label' => __('Project ID'), 'required' => true],
        ];
    }

    public static function features(): AccessKeyFeaturesData
    {
        return new AccessKeyFeaturesData;
    }

    public function users(): array
    {
        return [];
    }

    public function list(?string $user): array
    {
        $this->authenticate();

        $credentials = (array) $this->api()->get("/users/{$this->userId}/credentials/OS-EC2")->throw()->json('credentials');

        return array_values(array_map(fn (array $credential) => new AccessKeyData([
            'id' => (string) $credential['access'],
            'accessKeyId' => (string) $credential['access'],
            'description' => isset($credential['tenant_id']) ? __('Project :id', ['id' => $credential['tenant_id']]) : null,
            'user' => $this->username,
        ]), array_filter($credentials, fn (array $credential) => ($credential['tenant_id'] ?? $this->projectId) === $this->projectId)));
    }

    public function create(AccessKeyCreateData $data): AccessKeySecretData
    {
        $this->authenticate();

        $credential = (array) $this->api()->post("/users/{$this->userId}/credentials/OS-EC2", [
            'tenant_id' => $this->projectId,
        ])->throw()->json('credential');

        return new AccessKeySecretData([
            'accessKeyId' => (string) $credential['access'],
            'secretAccessKey' => (string) $credential['secret'],
        ]);
    }

    public function setStatus(string $id, ?string $user, bool $active): void
    {
        throw new DomainException(__('This provider cannot disable keys.'));
    }

    public function delete(string $id, ?string $user): void
    {
        $this->authenticate();

        $this->api()->delete("/users/{$this->userId}/credentials/OS-EC2/".rawurlencode($id))->throw();
    }

    private function authenticate(): void
    {
        if ($this->token !== null) {
            return;
        }

        $response = $this->http->baseUrl(rtrim($this->authUrl, '/'))->acceptJson()->timeout(20)->post('/auth/tokens', [
            'auth' => [
                'identity' => [
                    'methods' => ['password'],
                    'password' => ['user' => [
                        'name' => $this->username,
                        'domain' => ['name' => $this->domain],
                        'password' => $this->password,
                    ]],
                ],
                'scope' => ['project' => ['id' => $this->projectId]],
            ],
        ])->throw();

        $this->token = (string) $response->header('X-Subject-Token');
        $this->userId = (string) $response->json('token.user.id');
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(rtrim($this->authUrl, '/'))->acceptJson()->timeout(20)
            ->withHeaders(['X-Auth-Token' => (string) $this->token]);
    }
}
