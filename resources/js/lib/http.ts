export class HttpValidationError extends Error {
    constructor(public readonly errors: Record<string, string>) {
        super(Object.values(errors)[0] ?? 'Validation failed');
    }
}

/** Ошибка с текстом от сервера (например, отказ хранилища) */
export class HttpError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

const headers = (): Record<string, string> => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

/**
 * Разбор ответа: 422 с полями — HttpValidationError (первое сообщение по каждому полю),
 * другие ошибки — HttpError с сообщением сервера.
 */
function parse<T>(status: number, text: string): T {
    let data: unknown = null;

    try {
        data = text === '' ? null : JSON.parse(text);
    } catch {
        data = null;
    }

    const body = (data ?? {}) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (status === 422 && body.errors) {
        const errors: Record<string, string> = {};

        for (const [field, messages] of Object.entries(body.errors)) {
            errors[field] = messages[0] ?? '';
        }

        throw new HttpValidationError(errors);
    }

    if (status < 200 || status >= 300) {
        throw new HttpError(body.message || `HTTP ${status}`, status);
    }

    return data as T;
}

/**
 * JSON-запрос для действий без перехода по странице.
 */
export async function requestJson<T>(
    method: 'GET' | 'POST' | 'PUT' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<T> {
    const init: RequestInit = {
        method,
        credentials: 'same-origin',
        headers: headers(),
    };

    if (body !== undefined) {
        init.headers = { ...headers(), 'Content-Type': 'application/json' };
        init.body = JSON.stringify(body);
    }

    const response = await fetch(url, init);

    return parse<T>(response.status, await response.text());
}

export const getJson = <T>(url: string) => requestJson<T>('GET', url);

export const postJson = <T>(url: string, body: unknown) =>
    requestJson<T>('POST', url, body);

export type UploadHandle<T> = {
    promise: Promise<T>;
    abort: () => void;
};

/**
 * Отправка формы с файлом через XHR: у fetch нет прогресса отправки.
 */
export function sendForm<T>(
    url: string,
    form: FormData,
    onProgress: (loaded: number) => void,
): UploadHandle<T> {
    const xhr = new XMLHttpRequest();

    const promise = new Promise<T>((resolve, reject) => {
        xhr.open('POST', url);
        xhr.withCredentials = true;

        for (const [name, value] of Object.entries(headers())) {
            xhr.setRequestHeader(name, value);
        }

        xhr.upload.onprogress = (event) => onProgress(event.loaded);
        xhr.onload = () => {
            try {
                resolve(parse<T>(xhr.status, xhr.responseText));
            } catch (e) {
                reject(e as Error);
            }
        };
        xhr.onerror = () => reject(new HttpError('Network error', 0));
        xhr.onabort = () => reject(new DOMException('Aborted', 'AbortError'));
        xhr.send(form);
    });

    return { promise, abort: () => xhr.abort() };
}
