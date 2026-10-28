<?php

declare(strict_types=1);

namespace App\Domain\Enum\Storage;

/**
 * Поддерживаемые провайдеры. Значения хранятся в БД — не переименовывать.
 *
 * Настройки по умолчанию — подсказки для формы, пользователь может их изменить.
 */
enum StorageProvider: string
{
    case Aws = 'aws';
    case Yandex = 'yandex';
    case Selectel = 'selectel';
    case VkCloud = 'vk_cloud';
    case Minio = 'minio';
    case CloudflareR2 = 'cloudflare_r2';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Aws => 'AWS S3',
            self::Yandex => 'Yandex Object Storage',
            self::Selectel => 'Selectel',
            self::VkCloud => 'VK Cloud',
            self::Minio => 'MinIO',
            self::CloudflareR2 => 'Cloudflare R2',
            self::Generic => __('Other S3-compatible'),
        };
    }

    /**
     * Endpoint по умолчанию. null — пользователь вводит сам (у AWS endpoint не нужен).
     * {accountId} в R2 подставляет форма.
     */
    public function defaultEndpoint(): ?string
    {
        return match ($this) {
            self::Yandex => 'https://storage.yandexcloud.net',
            self::Selectel => 'https://s3.ru-1.storage.selcloud.ru',
            self::VkCloud => 'https://hb.ru-msk.vkcloud-storage.ru',
            self::CloudflareR2 => 'https://{accountId}.r2.cloudflarestorage.com',
            self::Aws, self::Minio, self::Generic => null,
        };
    }

    public function endpointRequired(): bool
    {
        return $this !== self::Aws;
    }

    public function defaultRegion(): string
    {
        return match ($this) {
            self::Aws, self::Minio, self::Generic => 'us-east-1',
            self::Yandex => 'ru-central1',
            self::Selectel => 'ru-1',
            self::VkCloud => 'ru-msk',
            self::CloudflareR2 => 'auto',
        };
    }

    public function defaultPathStyle(): bool
    {
        return match ($this) {
            self::Minio, self::Generic => true,
            default => false,
        };
    }
}
