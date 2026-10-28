export type UserStatus = 'active' | 'blocked' | 'deleted';

/** App\Domain\DTO\User\UserData */
export type User = {
    id: number;
    name: string;
    email: string;
    isSuperadmin: boolean;
    isActive: boolean;
    passwordChangeRequired: boolean;
    status: UserStatus;
    permissions: number[];
    lastLoginAt: string | null;
    createdAt: string | null;
    deletedAt: string | null;
};

export type Auth = {
    user: User;
    can: {
        viewUsers: boolean;
    };
};

export type TemporaryPassword = {
    email: string;
    password: string;
};
