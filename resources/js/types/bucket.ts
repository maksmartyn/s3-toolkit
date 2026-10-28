export type StorageCapability =
    | 'versioning'
    | 'object_lock'
    | 'bucket_policy'
    | 'cors'
    | 'lifecycle'
    | 'tagging'
    | 'acl'
    | 'website'
    | 'multipart_uploads';

export type OperationStatus = 'queued' | 'running' | 'completed' | 'failed';

/** App\Domain\DTO\Operation\OperationData */
export type Operation = {
    id: number;
    storageId: number;
    type:
        | 'bucket_delete'
        | 'bucket_stats'
        | 'folder_delete'
        | 'folder_copy'
        | 'folder_move';
    typeLabel: string;
    bucket: string;
    prefix: string | null;
    status: OperationStatus;
    statusLabel: string;
    processed: number;
    total: number | null;
    error: string | null;
    userId: number | null;
    startedAt: string | null;
    finishedAt: string | null;
    createdAt: string | null;
};

/** App\Domain\DTO\Bucket\BucketStatsData */
export type BucketStats = {
    objectCount: number;
    totalSize: number;
    versionCount: number | null;
    versionsSize: number | null;
    calculatedAt: string;
};

/** App\Domain\DTO\Bucket\BucketData */
export type Bucket = {
    name: string;
    createdAt: string | null;
    stats: BucketStats | null;
    operations: Operation[];
};

/** App\Domain\DTO\Bucket\BucketListData */
export type BucketList = {
    buckets: Bucket[];
    error: string | null;
};
