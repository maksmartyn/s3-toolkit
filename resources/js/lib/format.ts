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
