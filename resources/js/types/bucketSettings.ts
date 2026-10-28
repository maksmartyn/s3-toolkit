export type VersioningStatus = 'unversioned' | 'enabled' | 'suspended';

/** App\Domain\DTO\Bucket\BucketTagData */
export type BucketTag = {
    key: string;
    value: string;
};

/** App\Domain\DTO\Bucket\CorsRuleData */
export type CorsRule = {
    id: string | null;
    allowedOrigins: string[];
    allowedMethods: string[];
    allowedHeaders: string[];
    exposeHeaders: string[];
    maxAgeSeconds: number | null;
};

/** App\Domain\DTO\Bucket\BucketSectionData */
export type BucketSection<T> = {
    data: T;
    error: string | null;
};

/** Правило жизненного цикла в формате S3 API */
export type LifecycleRule = Record<string, unknown>;

/** App\Domain\DTO\Bucket\BucketAclData */
export type BucketAcl = {
    owner: string | null;
    grants: { grantee: string; type: string; permission: string }[];
    canned: CannedAcl | null;
};

export type CannedAcl =
    | 'private'
    | 'public-read'
    | 'public-read-write'
    | 'authenticated-read';

/** App\Domain\DTO\Bucket\WebsiteData */
export type BucketWebsite = {
    indexDocument: string | null;
    errorDocument: string | null;
    redirectHost: string | null;
    redirectProtocol: string | null;
    routingRules: string | null;
    url: string | null;
};

export type RetentionMode = 'GOVERNANCE' | 'COMPLIANCE';

/** App\Domain\DTO\Bucket\ObjectLockData */
export type BucketObjectLock = {
    enabled: boolean;
    mode: RetentionMode | null;
    days: number | null;
    years: number | null;
};
