<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Contracts;

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\DTO\AccessKey\AccessKeyData;
use App\Domain\DTO\AccessKey\AccessKeyFeaturesData;
use App\Domain\DTO\AccessKey\AccessKeySecretData;

/**
 * Управление ключами доступа к S3 через API провайдера. Ошибки провайдера — исключениями.
 */
interface AccessKeyManager
{
    /**
     * Поля учётных данных для управления (хранятся зашифрованными в storages.admin_credentials).
     *
     * @return list<array{name: string, label: string, secret?: bool, multiline?: bool, required?: bool, hint?: string, placeholder?: string, resetsSecrets?: bool}>
     */
    public static function fields(): array;

    public static function features(): AccessKeyFeaturesData;

    /**
     * @return list<string>
     */
    public function users(): array;

    /**
     * @return list<AccessKeyData>
     */
    public function list(?string $user): array;

    public function create(AccessKeyCreateData $data): AccessKeySecretData;

    public function setStatus(string $id, ?string $user, bool $active): void;

    public function delete(string $id, ?string $user): void;
}
