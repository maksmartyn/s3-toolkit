import type { LifecycleRule } from '@/types';

/**
 * Правила жизненного цикла: преобразование между форматом S3 и моделью формы.
 * Форма покрывает: фильтр по префиксу, срок хранения объектов, срок хранения старых версий,
 * удаление лишних маркеров удаления и очистку незавершённых загрузок.
 * Переходы между классами хранения, фильтры по тегам и даты — только в JSON.
 */
export type LifecycleRuleForm = {
    id: string;
    enabled: boolean;
    prefix: string;
    expirationDays: number | null;
    expiredDeleteMarker: boolean;
    noncurrentDays: number | null;
    abortDays: number | null;
};

const ALLOWED = new Set([
    'ID',
    'Status',
    'Filter',
    'Prefix',
    'Expiration',
    'NoncurrentVersionExpiration',
    'AbortIncompleteMultipartUpload',
]);

const isObject = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const onlyKeys = (value: unknown, keys: string[]): boolean =>
    isObject(value) && Object.keys(value).every((key) => keys.includes(key));

/** null — правила не помещаются в форму */
export function lifecycleToForm(
    rules: LifecycleRule[],
): LifecycleRuleForm[] | null {
    const result: LifecycleRuleForm[] = [];

    for (const rule of rules) {
        if (Object.keys(rule).some((key) => !ALLOWED.has(key))) {
            return null;
        }

        const emptyFilter =
            Array.isArray(rule.Filter) && rule.Filter.length === 0;

        if (
            rule.Filter !== undefined &&
            !emptyFilter &&
            !onlyKeys(rule.Filter, ['Prefix'])
        ) {
            return null;
        }

        if (
            rule.Expiration !== undefined &&
            !onlyKeys(rule.Expiration, ['Days', 'ExpiredObjectDeleteMarker'])
        ) {
            return null;
        }

        if (
            rule.NoncurrentVersionExpiration !== undefined &&
            !onlyKeys(rule.NoncurrentVersionExpiration, ['NoncurrentDays'])
        ) {
            return null;
        }

        const filter = isObject(rule.Filter) ? rule.Filter : {};
        const expiration = isObject(rule.Expiration) ? rule.Expiration : {};
        const noncurrent = isObject(rule.NoncurrentVersionExpiration)
            ? rule.NoncurrentVersionExpiration
            : {};
        const abort = isObject(rule.AbortIncompleteMultipartUpload)
            ? rule.AbortIncompleteMultipartUpload
            : {};

        result.push({
            id: typeof rule.ID === 'string' ? rule.ID : '',
            enabled: rule.Status !== 'Disabled',
            prefix:
                typeof filter.Prefix === 'string'
                    ? filter.Prefix
                    : typeof rule.Prefix === 'string'
                      ? rule.Prefix
                      : '',
            expirationDays:
                typeof expiration.Days === 'number' ? expiration.Days : null,
            expiredDeleteMarker: expiration.ExpiredObjectDeleteMarker === true,
            noncurrentDays:
                typeof noncurrent.NoncurrentDays === 'number'
                    ? noncurrent.NoncurrentDays
                    : null,
            abortDays:
                typeof abort.DaysAfterInitiation === 'number'
                    ? abort.DaysAfterInitiation
                    : null,
        });
    }

    return result;
}

export function lifecycleFromForm(rules: LifecycleRuleForm[]): LifecycleRule[] {
    return rules.map((rule) => ({
        ...(rule.id ? { ID: rule.id } : {}),
        Status: rule.enabled ? 'Enabled' : 'Disabled',
        Filter: { Prefix: rule.prefix },
        ...(rule.expirationDays !== null
            ? { Expiration: { Days: rule.expirationDays } }
            : rule.expiredDeleteMarker
              ? { Expiration: { ExpiredObjectDeleteMarker: true } }
              : {}),
        ...(rule.noncurrentDays !== null
            ? {
                  NoncurrentVersionExpiration: {
                      NoncurrentDays: rule.noncurrentDays,
                  },
              }
            : {}),
        ...(rule.abortDays !== null
            ? {
                  AbortIncompleteMultipartUpload: {
                      DaysAfterInitiation: rule.abortDays,
                  },
              }
            : {}),
    }));
}

export function emptyLifecycleRule(): LifecycleRuleForm {
    return {
        id: '',
        enabled: true,
        prefix: '',
        expirationDays: null,
        expiredDeleteMarker: false,
        noncurrentDays: 30,
        abortDays: 7,
    };
}
