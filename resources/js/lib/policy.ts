/**
 * Политика бакета: преобразование между JSON и моделью формы.
 *
 * Форма покрывает типичные политики: Effect, Principal (все или AWS-идентификаторы), Action, Resource и Condition (как JSON).
 * NotAction, NotResource, NotPrincipal и Principal с Service/Federated форма не показывает — такие политики правятся в JSON.
 */

export type PolicyStatementForm = {
    sid: string;
    effect: 'Allow' | 'Deny';
    principal: 'everyone' | 'aws';
    principals: string[];
    actions: string[];
    resources: string[];
    /** Condition как JSON-текст; пустая строка — условия нет */
    condition: string;
};

export type PolicyParseResult =
    | { ok: true; statements: PolicyStatementForm[] }
    | { ok: false; reason: string };

export const COMMON_ACTIONS = [
    's3:GetObject',
    's3:PutObject',
    's3:DeleteObject',
    's3:ListBucket',
    's3:GetBucketLocation',
    's3:ListBucketMultipartUploads',
    's3:AbortMultipartUpload',
    's3:*',
];

const SUPPORTED_KEYS = [
    'Sid',
    'Effect',
    'Principal',
    'Action',
    'Resource',
    'Condition',
];

function toList(value: unknown): string[] | null {
    if (typeof value === 'string') {
        return [value];
    }

    if (
        Array.isArray(value) &&
        value.every((item) => typeof item === 'string')
    ) {
        return value as string[];
    }

    return null;
}

/** Ключ перевода с причиной, если политика не помещается в форму */
export function parsePolicy(json: string | null): PolicyParseResult {
    if (json === null || json.trim() === '') {
        return { ok: true, statements: [] };
    }

    let policy: unknown;

    try {
        policy = JSON.parse(json);
    } catch {
        return { ok: false, reason: 'The JSON is invalid.' };
    }

    if (
        typeof policy !== 'object' ||
        policy === null ||
        Array.isArray(policy)
    ) {
        return { ok: false, reason: 'The policy must be a JSON object.' };
    }

    const raw = (policy as Record<string, unknown>).Statement;
    const statements = Array.isArray(raw) ? raw : raw ? [raw] : [];
    const result: PolicyStatementForm[] = [];

    for (const statement of statements) {
        if (typeof statement !== 'object' || statement === null) {
            return {
                ok: false,
                reason: 'The policy structure is not supported by the form.',
            };
        }

        const item = statement as Record<string, unknown>;

        if (Object.keys(item).some((key) => !SUPPORTED_KEYS.includes(key))) {
            return {
                ok: false,
                reason: 'The policy uses elements the form does not support (for example NotAction or NotPrincipal).',
            };
        }

        const actions = toList(item.Action);
        const resources = toList(item.Resource);

        if (
            (item.Effect !== 'Allow' && item.Effect !== 'Deny') ||
            actions === null ||
            resources === null
        ) {
            return {
                ok: false,
                reason: 'The policy structure is not supported by the form.',
            };
        }

        let principal: PolicyStatementForm['principal'] = 'everyone';
        let principals: string[] = [];
        const rawPrincipal = item.Principal;

        if (rawPrincipal !== '*') {
            const aws =
                typeof rawPrincipal === 'object' && rawPrincipal !== null
                    ? (rawPrincipal as Record<string, unknown>)
                    : null;

            if (aws === null || Object.keys(aws).some((key) => key !== 'AWS')) {
                return {
                    ok: false,
                    reason: 'The policy uses principals the form does not support (only everyone or AWS identifiers).',
                };
            }

            const list = toList(aws.AWS);

            if (list === null) {
                return {
                    ok: false,
                    reason: 'The policy structure is not supported by the form.',
                };
            }

            if (!(list.length === 1 && list[0] === '*')) {
                principal = 'aws';
                principals = list;
            }
        }

        result.push({
            sid: typeof item.Sid === 'string' ? item.Sid : '',
            effect: item.Effect,
            principal,
            principals,
            actions,
            resources,
            condition:
                item.Condition === undefined
                    ? ''
                    : JSON.stringify(item.Condition, null, 2),
        });
    }

    return { ok: true, statements: result };
}

/**
 * JSON политики из формы. Бросает SyntaxError, если в условии невалидный JSON.
 * Пустой список — политики нет (null).
 */
export function buildPolicy(statements: PolicyStatementForm[]): string | null {
    if (statements.length === 0) {
        return null;
    }

    const single = (list: string[]) => (list.length === 1 ? list[0] : list);

    return JSON.stringify(
        {
            Version: '2012-10-17',
            Statement: statements.map((statement) => ({
                ...(statement.sid ? { Sid: statement.sid } : {}),
                Effect: statement.effect,
                Principal:
                    statement.principal === 'everyone'
                        ? '*'
                        : { AWS: single(statement.principals) },
                Action: single(statement.actions),
                Resource: single(statement.resources),
                ...(statement.condition.trim()
                    ? { Condition: JSON.parse(statement.condition) }
                    : {}),
            })),
        },
        null,
        2,
    );
}

export function bucketArn(bucket: string): string {
    return `arn:aws:s3:::${bucket}`;
}

export function objectsArn(bucket: string): string {
    return `arn:aws:s3:::${bucket}/*`;
}

export type PolicyTemplate = {
    /** Ключ перевода */
    label: string;
    statement: (bucket: string) => PolicyStatementForm;
};

export const POLICY_TEMPLATES: PolicyTemplate[] = [
    {
        label: 'Public read access to objects',
        statement: (bucket) => ({
            sid: 'PublicRead',
            effect: 'Allow',
            principal: 'everyone',
            principals: [],
            actions: ['s3:GetObject'],
            resources: [objectsArn(bucket)],
            condition: '',
        }),
    },
    {
        label: 'Read-only access for an account',
        statement: (bucket) => ({
            sid: 'ReadOnly',
            effect: 'Allow',
            principal: 'aws',
            principals: [''],
            actions: ['s3:GetObject', 's3:ListBucket'],
            resources: [bucketArn(bucket), objectsArn(bucket)],
            condition: '',
        }),
    },
    {
        label: 'Deny requests without HTTPS',
        statement: (bucket) => ({
            sid: 'DenyInsecureTransport',
            effect: 'Deny',
            principal: 'everyone',
            principals: [],
            actions: ['s3:*'],
            resources: [bucketArn(bucket), objectsArn(bucket)],
            condition: JSON.stringify(
                { Bool: { 'aws:SecureTransport': 'false' } },
                null,
                2,
            ),
        }),
    },
];
