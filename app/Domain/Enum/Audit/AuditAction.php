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
    case BucketLifecycleChanged = 'bucket.lifecycle_changed';
    case BucketAclChanged = 'bucket.acl_changed';
    case BucketWebsiteChanged = 'bucket.website_changed';
    case BucketObjectLockChanged = 'bucket.object_lock_changed';

    case ObjectsDeleted = 'objects.deleted';
    case ObjectsMoved = 'objects.moved';
    case ObjectLinkCreated = 'object.link_created';
    case ObjectAclChanged = 'object.acl_changed';
    case ObjectRetentionChanged = 'object.retention_changed';
    case ObjectLegalHoldChanged = 'object.legal_hold_changed';
    case ObjectVersionRestored = 'object.version_restored';
    case UploadsAborted = 'objects.uploads_aborted';
    case FolderDeletionStarted = 'folder.deletion_started';
    case FolderDeleted = 'folder.deleted';
    case FolderMoveStarted = 'folder.move_started';
    case FolderMoved = 'folder.moved';

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
            self::BucketCorsChanged,
            self::BucketLifecycleChanged,
            self::BucketAclChanged,
            self::BucketWebsiteChanged,
            self::BucketObjectLockChanged,
            self::ObjectsDeleted,
            self::ObjectsMoved,
            self::ObjectLinkCreated,
            self::ObjectAclChanged,
            self::ObjectRetentionChanged,
            self::ObjectLegalHoldChanged,
            self::ObjectVersionRestored,
            self::UploadsAborted,
            self::FolderDeletionStarted,
            self::FolderDeleted,
            self::FolderMoveStarted,
            self::FolderMoved => AuditSubjectType::Bucket,
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
            self::BucketLifecycleChanged => __('Bucket lifecycle changed'),
            self::BucketAclChanged => __('Bucket ACL changed'),
            self::BucketWebsiteChanged => __('Static website hosting changed'),
            self::BucketObjectLockChanged => __('Bucket Object Lock changed'),
            self::ObjectsDeleted => __('Objects deleted'),
            self::ObjectsMoved => __('Objects moved'),
            self::ObjectLinkCreated => __('Temporary link created'),
            self::ObjectAclChanged => __('Object ACL changed'),
            self::ObjectRetentionChanged => __('Object retention changed'),
            self::ObjectLegalHoldChanged => __('Object legal hold changed'),
            self::ObjectVersionRestored => __('Object version restored'),
            self::UploadsAborted => __('Unfinished uploads aborted'),
            self::FolderDeletionStarted => __('Folder deletion started'),
            self::FolderDeleted => __('Folder deleted'),
            self::FolderMoveStarted => __('Folder move started'),
            self::FolderMoved => __('Folder moved'),
        };
    }
}
