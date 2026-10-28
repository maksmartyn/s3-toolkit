<?php

declare(strict_types=1);

namespace App\Domain\Enum\Permission;

use App\Domain\Enum\Permission\Contracts\BitPermission;

/**
 * Права пользователя на конкретное хранилище. Значение — номер бита в storage_user.permissions.
 *
 * Номера битов фиксированы навсегда: нельзя менять и переиспользовать.
 */
enum StoragePermission: int implements BitPermission
{
    case ViewBuckets = 0;
    case CreateBuckets = 1;
    case DeleteBuckets = 2;
    case ManageAccessKeys = 3;
    case ManageBucketPolicy = 4;
    case ManageCors = 5;
    case ManageLifecycle = 6;
    case ManageVersioning = 7;
    case ManageBucketTags = 8;
    case ManageAcl = 9;
    case ManageWebsite = 10;
    case ManageObjectLock = 11;
    case ViewObjects = 12;
    case PutObjects = 13;
    case DeleteObjects = 14;
    case SharePresignedLinks = 15;

    public function bit(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewBuckets => __('View buckets and their settings'),
            self::CreateBuckets => __('Create buckets'),
            self::DeleteBuckets => __('Delete buckets'),
            self::ManageAccessKeys => __('Manage access keys'),
            self::ManageBucketPolicy => __('Manage bucket policies'),
            self::ManageCors => __('Manage CORS'),
            self::ManageLifecycle => __('Manage lifecycle rules'),
            self::ManageVersioning => __('Manage versioning'),
            self::ManageBucketTags => __('Manage bucket tags'),
            self::ManageAcl => __('Manage ACL and public access'),
            self::ManageWebsite => __('Manage static website hosting'),
            self::ManageObjectLock => __('Manage Object Lock'),
            self::ViewObjects => __('View and download objects'),
            self::PutObjects => __('Upload, copy and move objects'),
            self::DeleteObjects => __('Delete objects'),
            self::SharePresignedLinks => __('Create temporary public links'),
        };
    }
}
