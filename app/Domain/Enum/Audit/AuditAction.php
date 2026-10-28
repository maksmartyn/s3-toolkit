<?php

declare(strict_types=1);

namespace App\Domain\Enum\Audit;

/**
 * Действия, которые пишутся в журнал. Значения хранятся в БД — не переименовывать.
 */
enum AuditAction: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserBlocked = 'user.blocked';
    case UserUnblocked = 'user.unblocked';
    case UserDeleted = 'user.deleted';
    case UserRestored = 'user.restored';
    case UserPasswordReset = 'user.password_reset';
    case UserPasswordChanged = 'user.password_changed';
    case UserPermissionsChanged = 'user.permissions_changed';

    case PermissionTemplateCreated = 'permission_template.created';
    case PermissionTemplateUpdated = 'permission_template.updated';
    case PermissionTemplateDeleted = 'permission_template.deleted';

    case StorageCreated = 'storage.created';
    case StorageUpdated = 'storage.updated';
    case StorageDeleted = 'storage.deleted';
    case StorageAccessChanged = 'storage.access_changed';

    case BucketCreated = 'bucket.created';
    case BucketDeletionStarted = 'bucket.deletion_started';
    case BucketDeleted = 'bucket.deleted';
    case BucketVersioningChanged = 'bucket.versioning_changed';
    case BucketTagsChanged = 'bucket.tags_changed';
    case BucketPolicyChanged = 'bucket.policy_changed';
    case BucketCorsChanged = 'bucket.cors_changed';

    public function subjectType(): AuditSubjectType
    {
        return match ($this) {
            self::UserCreated,
            self::UserUpdated,
            self::UserBlocked,
            self::UserUnblocked,
            self::UserDeleted,
            self::UserRestored,
            self::UserPasswordReset,
            self::UserPasswordChanged,
            self::UserPermissionsChanged => AuditSubjectType::User,

            self::PermissionTemplateCreated,
            self::PermissionTemplateUpdated,
            self::PermissionTemplateDeleted => AuditSubjectType::PermissionTemplate,

            self::StorageCreated,
            self::StorageUpdated,
            self::StorageDeleted,
            self::StorageAccessChanged => AuditSubjectType::Storage,

            self::BucketCreated,
            self::BucketDeletionStarted,
            self::BucketDeleted,
            self::BucketVersioningChanged,
            self::BucketTagsChanged,
            self::BucketPolicyChanged,
            self::BucketCorsChanged => AuditSubjectType::Bucket,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UserCreated => __('User created'),
            self::UserUpdated => __('User updated'),
            self::UserBlocked => __('User blocked'),
            self::UserUnblocked => __('User unblocked'),
            self::UserDeleted => __('User deleted'),
            self::UserRestored => __('User restored'),
            self::UserPasswordReset => __('Password reset'),
            self::UserPasswordChanged => __('Password changed'),
            self::UserPermissionsChanged => __('System permissions changed'),
            self::PermissionTemplateCreated => __('Permission template created'),
            self::PermissionTemplateUpdated => __('Permission template updated'),
            self::PermissionTemplateDeleted => __('Permission template deleted'),
            self::StorageCreated => __('Storage added'),
            self::StorageUpdated => __('Storage updated'),
            self::StorageDeleted => __('Storage deleted'),
            self::StorageAccessChanged => __('Storage permissions changed'),
            self::BucketCreated => __('Bucket created'),
            self::BucketDeletionStarted => __('Bucket deletion started'),
            self::BucketDeleted => __('Bucket deleted'),
            self::BucketVersioningChanged => __('Bucket versioning changed'),
            self::BucketTagsChanged => __('Bucket tags changed'),
            self::BucketPolicyChanged => __('Bucket policy changed'),
            self::BucketCorsChanged => __('Bucket CORS changed'),
        };
    }
}
