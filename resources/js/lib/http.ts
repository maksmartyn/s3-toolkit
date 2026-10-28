export class HttpValidationError extends Error {
    constructor(public readonly errors: Record<string, string>) {
        super('Validation failed');
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * JSON-запрос для действий без перехода по странице (например, проверка подключения).
 * Ошибки валидации (422) превращаются в HttpValidationError с первым сообщением по каждому полю.
 */
export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (response.status === 422) {
        const data = (await response.json()) as {
            errors?: Record<string, string[]>;
        };
        const errors: Record<string, string> = {};

        for (const [field, messages] of Object.entries(data.errors ?? {})) {
            errors[field] = messages[0] ?? '';
        }

        throw new HttpValidationError(errors);
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}
