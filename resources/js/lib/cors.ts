import type { CorsRule } from '@/types';

export const CORS_METHODS = ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'];

/**
 * Правила CORS в формате консоли AWS (массив объектов AllowedOrigins, AllowedMethods, ...).
 */
export function corsToJson(rules: CorsRule[]): string {
    return JSON.stringify(
        rules.map((rule) => ({
            ...(rule.id ? { ID: rule.id } : {}),
            AllowedOrigins: rule.allowedOrigins,
            AllowedMethods: rule.allowedMethods,
            ...(rule.allowedHeaders.length
                ? { AllowedHeaders: rule.allowedHeaders }
                : {}),
            ...(rule.exposeHeaders.length
                ? { ExposeHeaders: rule.exposeHeaders }
                : {}),
            ...(rule.maxAgeSeconds !== null
                ? { MaxAgeSeconds: rule.maxAgeSeconds }
                : {}),
        })),
        null,
        2,
    );
}

const strings = (value: unknown): string[] =>
    Array.isArray(value) ? value.map((item) => String(item)) : [];

/**
 * Разбор JSON правил. Принимает и массив правил, и объект {"CORSRules": [...]}.
 * Бросает Error с ключом перевода, если формат не подходит.
 */
export function corsFromJson(json: string): CorsRule[] {
    if (json.trim() === '') {
        return [];
    }

    let value: unknown = JSON.parse(json);

    if (
        typeof value === 'object' &&
        value !== null &&
        !Array.isArray(value) &&
        'CORSRules' in value
    ) {
        value = (value as { CORSRules: unknown }).CORSRules;
    }

    if (!Array.isArray(value)) {
        throw new Error('CORS rules must be a JSON array.');
    }

    return value.map((item) => {
        if (typeof item !== 'object' || item === null) {
            throw new Error('CORS rules must be a JSON array.');
        }

        const rule = item as Record<string, unknown>;

        return {
            id: typeof rule.ID === 'string' ? rule.ID : null,
            allowedOrigins: strings(rule.AllowedOrigins),
            allowedMethods: strings(rule.AllowedMethods).map((method) =>
                method.toUpperCase(),
            ),
            allowedHeaders: strings(rule.AllowedHeaders),
            exposeHeaders: strings(rule.ExposeHeaders),
            maxAgeSeconds:
                typeof rule.MaxAgeSeconds === 'number'
                    ? rule.MaxAgeSeconds
                    : null,
        };
    });
}

export function emptyCorsRule(): CorsRule {
    return {
        id: null,
        allowedOrigins: [''],
        allowedMethods: ['GET'],
        allowedHeaders: [],
        exposeHeaders: [],
        maxAgeSeconds: 3000,
    };
}
