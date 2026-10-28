export type AccessKeyStatus = 'active' | 'inactive' | 'expired';

/** App\Domain\DTO\AccessKey\AccessKeyData */
export type AccessKey = {
    id: string;
    accessKeyId: string;
    name: string | null;
    description: string | null;
    user: string | null;
    status: AccessKeyStatus;
    createdAt: string | null;
    expiresAt: string | null;
};

/** App\Domain\DTO\AccessKey\AccessKeyFeaturesData */
export type AccessKeyFeatures = {
    users: boolean;
    status: boolean;
    expiration: boolean;
    policy: boolean;
    name: boolean;
    description: boolean;
};

/** App\Domain\DTO\AccessKey\AdminCredentialFieldData */
export type AdminCredentialField = {
    name: string;
    label: string;
    secret: boolean;
    multiline: boolean;
    required: boolean;
    hint: string | null;
    placeholder: string | null;
    value: string | null;
    isSet: boolean;
};

/** App\Domain\DTO\AccessKey\AccessKeySettingsData */
export type AccessKeySettings = {
    supported: boolean;
    configured: boolean;
    features: AccessKeyFeatures | null;
    fields: AdminCredentialField[];
};

/** App\Domain\DTO\AccessKey\AccessKeyOverviewData */
export type AccessKeyOverview = {
    keys: AccessKey[];
    users: string[];
    user: string | null;
    error: string | null;
};

/** App\Domain\DTO\AccessKey\AccessKeySecretData */
export type AccessKeySecret = {
    accessKeyId: string;
    secretAccessKey: string;
};
