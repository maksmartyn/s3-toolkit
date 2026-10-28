import { trans } from 'laravel-vue-i18n';
import { computed, reactive } from 'vue';
import ObjectUploadController from '@/actions/App/Http/Controllers/Bucket/ObjectUploadController';
import { requestJson, sendForm } from '@/lib/http';

export type UploadStatus =
    | 'queued'
    | 'uploading'
    | 'done'
    | 'failed'
    | 'cancelled';

export type UploadItem = {
    id: number;
    file: File;
    key: string;
    loaded: number;
    status: UploadStatus;
    error: string | null;
};

/** Сколько файлов загружается одновременно */
const CONCURRENCY = 2;

/**
 * Очередь загрузки в бакет через сервер. Файлы до partSize — одним запросом, крупнее — частями (S3 multipart).
 * Части отправляются по очереди: сервер держит в памяти и на диске не больше одной части на запрос.
 */
export function useUploads(
    storage: number,
    bucket: string,
    partSize: number,
    onUploaded: () => void,
) {
    const items = reactive<UploadItem[]>([]);
    const aborts = new Map<number, () => void>();
    const cancelled = new Set<number>();
    let sequence = 0;

    const args = { storage, bucket };

    const active = computed(() =>
        items.filter(
            (item) => item.status === 'queued' || item.status === 'uploading',
        ),
    );
    const totalBytes = computed(() =>
        active.value.reduce((sum, item) => sum + item.file.size, 0),
    );
    const loadedBytes = computed(() =>
        active.value.reduce((sum, item) => sum + item.loaded, 0),
    );

    function add(files: { file: File; key: string }[]): void {
        for (const { file, key } of files) {
            items.push({
                id: ++sequence,
                file,
                key,
                loaded: 0,
                status: 'queued',
                error: null,
            });
        }

        pump();
    }

    function pump(): void {
        const running = items.filter(
            (item) => item.status === 'uploading',
        ).length;
        const next = items
            .filter((item) => item.status === 'queued')
            .slice(0, Math.max(0, CONCURRENCY - running));

        for (const item of next) {
            void run(item);
        }
    }

    async function run(item: UploadItem): Promise<void> {
        item.status = 'uploading';

        try {
            if (item.file.size <= partSize) {
                await single(item);
            } else {
                await multipart(item);
            }

            item.status = 'done';
            item.loaded = item.file.size;
            onUploaded();
        } catch (e) {
            if (cancelled.has(item.id)) {
                item.status = 'cancelled';
            } else {
                item.status = 'failed';
                item.error =
                    e instanceof Error ? e.message : trans('Upload failed.');
            }
        } finally {
            aborts.delete(item.id);
            pump();
        }
    }

    async function single(item: UploadItem): Promise<void> {
        const form = new FormData();
        form.append('key', item.key);
        form.append('file', item.file);

        const handle = sendForm(
            ObjectUploadController.store.url(args),
            form,
            (loaded) => (item.loaded = Math.min(loaded, item.file.size)),
        );
        aborts.set(item.id, handle.abort);
        await handle.promise;
    }

    async function multipart(item: UploadItem): Promise<void> {
        const { uploadId } = await requestJson<{ uploadId: string }>(
            'POST',
            ObjectUploadController.start.url(args),
            { key: item.key, content_type: item.file.type || null },
        );

        const abortUpload = () =>
            requestJson('DELETE', ObjectUploadController.abort.url(args), {
                key: item.key,
                upload_id: uploadId,
            }).catch(() => undefined);

        const parts: { part_number: number; etag: string }[] = [];
        const count = Math.ceil(item.file.size / partSize);

        try {
            for (let number = 1; number <= count; number++) {
                if (cancelled.has(item.id)) {
                    throw new DOMException('Aborted', 'AbortError');
                }

                const offset = (number - 1) * partSize;
                const form = new FormData();
                form.append('key', item.key);
                form.append('upload_id', uploadId);
                form.append('part_number', String(number));
                form.append(
                    'file',
                    item.file.slice(offset, offset + partSize),
                    'part',
                );

                const handle = sendForm<{ etag: string }>(
                    ObjectUploadController.part.url(args),
                    form,
                    (loaded) =>
                        (item.loaded = Math.min(
                            offset + loaded,
                            item.file.size,
                        )),
                );
                aborts.set(item.id, handle.abort);
                const { etag } = await handle.promise;
                parts.push({ part_number: number, etag });
            }

            await requestJson(
                'POST',
                ObjectUploadController.complete.url(args),
                { key: item.key, upload_id: uploadId, parts },
            );
        } catch (e) {
            await abortUpload();

            throw e;
        }
    }

    function cancel(item: UploadItem): void {
        cancelled.add(item.id);

        if (item.status === 'queued') {
            item.status = 'cancelled';

            return;
        }

        aborts.get(item.id)?.();
    }

    function retry(item: UploadItem): void {
        cancelled.delete(item.id);
        item.status = 'queued';
        item.loaded = 0;
        item.error = null;
        pump();
    }

    function clearFinished(): void {
        for (let i = items.length - 1; i >= 0; i--) {
            if (
                items[i].status !== 'queued' &&
                items[i].status !== 'uploading'
            ) {
                items.splice(i, 1);
            }
        }
    }

    return {
        items,
        active,
        totalBytes,
        loadedBytes,
        add,
        cancel,
        retry,
        clearFinished,
    };
}
