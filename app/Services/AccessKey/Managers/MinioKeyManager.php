<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Managers;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Domain\Enum\AccessKey\AccessKeyStatus;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use App\Services\AccessKey\Support\McRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ключи MinIO — сервисные аккаунты пользователя (mc admin user svcacct). Ключ и секрет генерируются здесь:
 * mc не всегда выводит созданный секрет.
 */
final readonly class MinioKeyManager implements AccessKeyManager
{
    /** Сколько ключей показываем: сведения о каждом — отдельный вызов mc */
    private const int MAX_KEYS = 100;

    public function __construct(
        private McRunner $mc,
        private string $endpoint,
        private string $accessKeyId,
        private string $secretAccessKey,
        private bool $verifySsl,
        private string $defaultUser,
    ) {}

    public static function fields(): array
    {
        return [
            [
                'name' => 'access_key_id',
                'label' => __('Admin access key'),
                'hint' => __('Empty — use the storage keys. Needs the admin:CreateServiceAccount, admin:ListServiceAccounts, admin:UpdateServiceAccount, admin:RemoveServiceAccount and admin:ListUsers permissions (for example the root user).'),
            ],
            ['name' => 'secret_access_key', 'label' => __('Admin secret key'), 'secret' => true],
            [
                'name' => 'user',
                'label' => __('Default user'),
                'hint' => __('Whose keys to show first. Empty — the admin key owner.'),
            ],
        ];
    }

    public static function features(): AccessKeyFeaturesData
    {
        return new AccessKeyFeaturesData([
            'users' => true,
            'status' => true,
            'expiration' => true,
            'policy' => true,
            'name' => true,
            'description' => true,
        ]);
    }

    public function users(): array
    {
        $users = array_map(
            fn (array $line) => (string) ($line['accessKey'] ?? ''),
            $this->run(['admin', 'user', 'list', '{alias}']),
        );

        // Ключ администратора в списке не показываем: его ключи — это «учётная запись управления» по умолчанию
        return array_values(array_unique(array_filter($users, fn (string $user) => $user !== '' && $user !== $this->accessKeyId)));
    }

    public function list(?string $user): array
    {
        $owner = $user ?? $this->defaultUser;
        $lines = array_slice($this->run(['admin', 'user', 'svcacct', 'ls', '{alias}', $owner]), 0, self::MAX_KEYS);

        return array_map(function (array $line) use ($owner): AccessKeyData {
            $accessKey = (string) $line['accessKey'];

            try {
                $info = $this->run(['admin', 'user', 'svcacct', 'info', '{alias}', $accessKey])[0] ?? [];
            } catch (Throwable $e) {
                report_if(config('app.debug'), $e);
                $info = [];
            }

            $expires = isset($info['expiration']) ? CarbonImmutable::parse((string) $info['expiration']) : null;

            // «Без срока» MinIO отдаёт как нулевую дату
            if ($expires !== null && $expires->year < 2000) {
                $expires = null;
            }

            return new AccessKeyData([
                'id' => $accessKey,
                'accessKeyId' => $accessKey,
                'name' => ($info['name'] ?? '') !== '' ? (string) $info['name'] : null,
                'description' => ($info['description'] ?? '') !== '' ? (string) $info['description'] : null,
                'user' => isset($info['parentUser']) ? (string) $info['parentUser'] : $owner,
                'status' => match (true) {
                    $expires !== null && $expires->isPast() => AccessKeyStatus::Expired,
                    ($info['accountStatus'] ?? 'on') === 'off' => AccessKeyStatus::Inactive,
                    default => AccessKeyStatus::Active,
                },
                'expiresAt' => $expires,
            ]);
        }, $lines);
    }

    public function create(AccessKeyCreateData $data): AccessKeySecretData
    {
        $accessKey = strtoupper(Str::random(20));
        $secret = Str::random(40);
        $policyFile = null;

        $args = ['admin', 'user', 'svcacct', 'add', '{alias}', $data->user ?? $this->defaultUser, '--access-key', $accessKey, '--secret-key', $secret];

        if ($data->name !== null) {
            array_push($args, '--name', $data->name);
        }

        if ($data->description !== null) {
            array_push($args, '--description', $data->description);
        }

        if ($data->expiresAt !== null) {
            array_push($args, '--expiry', $data->expiresAt->utc()->format('Y-m-d\TH:i:s\Z'));
        }

        try {
            if ($data->policy !== null) {
                $policyFile = tempnam(sys_get_temp_dir(), 'policy');
                file_put_contents((string) $policyFile, $data->policy);
                array_push($args, '--policy', (string) $policyFile);
            }

            $this->run($args);
        } finally {
            if (is_string($policyFile)) {
                @unlink($policyFile);
            }
        }

        return new AccessKeySecretData(['accessKeyId' => $accessKey, 'secretAccessKey' => $secret]);
    }

    public function setStatus(string $id, ?string $user, bool $active): void
    {
        $this->run(['admin', 'user', 'svcacct', $active ? 'enable' : 'disable', '{alias}', $id]);
    }

    public function delete(string $id, ?string $user): void
    {
        $this->run(['admin', 'user', 'svcacct', 'rm', '{alias}', $id]);
    }

    /**
     * @param  list<string>  $args
     * @return list<array<string, mixed>>
     */
    private function run(array $args): array
    {
        return $this->mc->run($this->endpoint, $this->accessKeyId, $this->secretAccessKey, $this->verifySsl, $args);
    }
}
