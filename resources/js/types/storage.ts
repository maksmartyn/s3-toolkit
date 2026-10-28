import type { UserStatus } from './auth';

export type StorageProvider =
    | 'aws'
    | 'yandex'
    | 'selectel'
    | 'vk_cloud'
    | 'minio'
    | 'cloudflare_r2'
    | 'generic';

/** App\Domain\DTO\Storage\StorageData */
export type Storage = {
    id: number;
    name: string;
    provider: StorageProvider;
    providerLabel: string;
    permissions: number[];
};

/** App\Domain\DTO\Storage\StorageDetailsData */
export type StorageDetails = {
    id: number;
    name: string;
    provider: StorageProvider;
    providerLabel: string;
    endpoint: string | null;
    region: string;
    usePathStyle: boolean;
    verifySsl: boolean;
    accessKeyIdMasked: string;
    buckets: string[] | null;
    createdAt: string | null;
    updatedAt: string | null;
};

/** App\Domain\DTO\Storage\ProviderPresetData */
export type ProviderPreset = {
    value: StorageProvider;
    label: string;
    defaultEndpoint: string | null;
    endpointRequired: boolean;
    defaultRegion: string;
    defaultPathStyle: boolean;
};

/** App\Domain\DTO\Storage\ConnectionTestResultData */
export type ConnectionTestResult = {
    ok: boolean;
    message: string;
    buckets: string[];
    bucketCount: number | null;
};

/** App\Domain\DTO\Storage\StorageAccessData */
export type StorageAccess = {
    userId: number;
    userName: string;
    userEmail: string;
    userStatus: UserStatus;
    permissions: number[];
    updatedAt: string | null;
};
