import type { BucketTag, RetentionMode } from './bucketSettings';

/** App\Domain\DTO\Object\ObjectItemData */
export type ObjectItem = {
    key: string;
    name: string;
    size: number;
    lastModified: string | null;
    storageClass: string | null;
    etag: string | null;
};

/** App\Domain\DTO\Object\ObjectListData */
export type ObjectList = {
    prefix: string;
    folders: string[];
    objects: ObjectItem[];
    next: string | null;
    error: string | null;
};

/** App\Domain\DTO\Object\ObjectDetailsData */
export type ObjectDetails = {
    key: string;
    size: number;
    contentType: string | null;
    cacheControl: string | null;
    contentDisposition: string | null;
    contentEncoding: string | null;
    etag: string | null;
    lastModified: string | null;
    storageClass: string | null;
    versionId: string | null;
    metadata: Record<string, string>;
    tags: BucketTag[] | null;
};

/** App\Domain\DTO\Object\ObjectVersionData */
export type ObjectVersion = {
    versionId: string | null;
    size: number;
    lastModified: string | null;
    isLatest: boolean;
    isDeleteMarker: boolean;
};

/** App\Domain\DTO\Object\ObjectProtectionData */
export type ObjectProtection = {
    mode: RetentionMode | null;
    until: string | null;
    legalHold: boolean;
};

/** App\Domain\DTO\Object\MultipartUploadData */
export type MultipartUpload = {
    key: string;
    uploadId: string;
    initiated: string | null;
};

export type ObjectPermissions = {
    put: boolean;
    delete: boolean;
    share: boolean;
    manageAcl: boolean;
    manageObjectLock: boolean;
    bucketSettings: boolean;
};

export type ObjectCapabilities = {
    versioning: boolean;
    tagging: boolean;
    acl: boolean;
    objectLock: boolean;
    uploads: boolean;
};
