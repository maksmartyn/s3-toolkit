<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Managers;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;
use App\Domain\Enum\AccessKey\AccessKeyStatus;
use App\Services\AccessKey\Contracts\AccessKeyManager;
use Aws\Iam\IamClient;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Ключи пользователей AWS IAM. Без пользователя — ключи того, чьими ключами выполняется запрос.
 */
final readonly class AwsIamKeyManager implements AccessKeyManager
{
    public function __construct(private IamClient $iam) {}

    public static function fields(): array
    {
        return [
            [
                'name' => 'access_key_id',
                'label' => __('IAM access key ID'),
                'hint' => __('Empty — use the storage keys. The key needs IAM permissions: iam:ListUsers, iam:ListAccessKeys, iam:CreateAccessKey, iam:UpdateAccessKey, iam:DeleteAccessKey.'),
            ],
            ['name' => 'secret_access_key', 'label' => __('IAM secret key'), 'secret' => true],
        ];
    }

    public static function features(): AccessKeyFeaturesData
    {
        return new AccessKeyFeaturesData(['users' => true, 'status' => true]);
    }

    public function users(): array
    {
        $users = [];

        foreach ($this->iam->getPaginator('ListUsers') as $page) {
            foreach ((array) $page->get('Users') as $user) {
                $users[] = (string) $user['UserName'];
            }
        }

        return $users;
    }

    public function list(?string $user): array
    {
        $keys = [];

        foreach ($this->iam->getPaginator('ListAccessKeys', array_filter(['UserName' => $user])) as $page) {
            foreach ((array) $page->get('AccessKeyMetadata') as $key) {
                $created = $key['CreateDate'] ?? null;

                $keys[] = new AccessKeyData([
                    'id' => (string) $key['AccessKeyId'],
                    'accessKeyId' => (string) $key['AccessKeyId'],
                    'user' => isset($key['UserName']) ? (string) $key['UserName'] : $user,
                    'status' => ($key['Status'] ?? '') === 'Active' ? AccessKeyStatus::Active : AccessKeyStatus::Inactive,
                    'createdAt' => $created instanceof DateTimeInterface ? CarbonImmutable::instance($created) : null,
                ]);
            }
        }

        return $keys;
    }

    public function create(AccessKeyCreateData $data): AccessKeySecretData
    {
        $key = (array) $this->iam->createAccessKey(array_filter(['UserName' => $data->user]))->get('AccessKey');

        return new AccessKeySecretData([
            'accessKeyId' => (string) $key['AccessKeyId'],
            'secretAccessKey' => (string) $key['SecretAccessKey'],
        ]);
    }

    public function setStatus(string $id, ?string $user, bool $active): void
    {
        $this->iam->updateAccessKey(array_filter([
            'AccessKeyId' => $id,
            'UserName' => $user,
            'Status' => $active ? 'Active' : 'Inactive',
        ]));
    }

    public function delete(string $id, ?string $user): void
    {
        $this->iam->deleteAccessKey(array_filter(['AccessKeyId' => $id, 'UserName' => $user]));
    }
}
