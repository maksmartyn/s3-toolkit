export type AuditSubjectType = 'user' | 'permission_template';

/** App\Domain\DTO\Audit\AuditEntryData */
export type AuditEntry = {
    id: number;
    createdAt: string;
    actorId: number | null;
    actorName: string | null;
    actorEmail: string | null;
    action: string;
    actionLabel: string;
    subjectType: AuditSubjectType;
    subjectId: number | null;
    subjectLabel: string | null;
    lines: string[];
    source: 'web' | 'system';
    ip: string | null;
    userAgent: string | null;
};

export type AuditFilters = {
    search: string | null;
    action: string | null;
    user_id: number | null;
    date_from: string | null;
    date_to: string | null;
};
