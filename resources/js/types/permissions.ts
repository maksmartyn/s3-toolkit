export type PermissionScope = 'system' | 'storage';

/** App\Domain\DTO\Permission\PermissionOptionData */
export type PermissionOption = {
    value: number;
    name: string;
    label: string;
};

export type PermissionScopeData = {
    value: PermissionScope;
    label: string;
    options: PermissionOption[];
};

/** App\Domain\DTO\Permission\PermissionTemplateData */
export type PermissionTemplate = {
    id: number;
    name: string;
    scope: PermissionScope;
    permissions: number[];
    updatedAt: string | null;
};
