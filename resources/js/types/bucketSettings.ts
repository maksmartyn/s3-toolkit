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
