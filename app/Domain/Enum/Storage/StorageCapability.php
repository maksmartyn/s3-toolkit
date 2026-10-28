<?php

declare(strict_types=1);

namespace App\Domain\Enum\Storage;

/**
 * Необязательные функции S3 API, которые поддерживают не все провайдеры.
 * Значения хранятся в БД (переопределения по хранилищу) — не переименовывать.
 */
enum StorageCapability: string
{
    case Versioning = 'versioning';
    case ObjectLock = 'object_lock';
    case BucketPolicy = 'bucket_policy';
    case Cors = 'cors';
    case Lifecycle = 'lifecycle';
    case Tagging = 'tagging';
    case Acl = 'acl';
    case Website = 'website';
    case MultipartUploads = 'multipart_uploads';

    public function label(): string
    {
        return match ($this) {
            self::Versioning => __('Versioning'),
            self::ObjectLock => __('Object Lock'),
            self::BucketPolicy => __('Bucket policies'),
            self::Cors => __('CORS'),
            self::Lifecycle => __('Lifecycle rules'),
            self::Tagging => __('Tags'),
            self::Acl => __('ACL'),
            self::Website => __('Static website hosting'),
            self::MultipartUploads => __('Listing multipart uploads'),
        };
    }

    /**
     * Поддержка по умолчанию. Это лучшая оценка по документации провайдеров;
     * если она неверна для конкретного хранилища, её переопределяют в настройках хранилища.
     */
    public function supportedBy(StorageProvider $provider): bool
    {
        return match ($provider) {
            StorageProvider::Aws, StorageProvider::Yandex, StorageProvider::Generic => true,
            StorageProvider::Minio => ! in_array($this, [self::Acl, self::Website, self::Cors], true),
            StorageProvider::Selectel, StorageProvider::VkCloud => $this !== self::ObjectLock,
            StorageProvider::CloudflareR2 => in_array($this, [self::Cors, self::Lifecycle, self::MultipartUploads], true),
        };
    }
}
