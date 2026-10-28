export function formatDateTime(
    value: string | null,
    locale: string,
): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

/** Размер в двоичных единицах: 1536 → «1,5 KB». */
export function formatBytes(bytes: number, locale: string): string {
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < BYTE_UNITS.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${new Intl.NumberFormat(locale, { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${BYTE_UNITS[unit]}`;
}

export function formatNumber(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}
