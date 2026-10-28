<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    ArrowLeft,
    ChevronRight,
    CircleX,
    Copy,
    Download,
    File as FileIcon,
    Folder,
    FolderInput,
    RefreshCw,
    Search,
    Settings,
    Trash2,
    Upload,
} from '@lucide/vue';
import { useDebounceFn, useThrottleFn } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { toast } from 'vue-sonner';
import { computed, ref, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import ObjectController from '@/actions/App/Http/Controllers/Bucket/ObjectController';
import ObjectManageController from '@/actions/App/Http/Controllers/Bucket/ObjectManageController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import MultipartUploadsDialog from '@/components/objects/MultipartUploadsDialog.vue';
import NewFolderDialog from '@/components/objects/NewFolderDialog.vue';
import ObjectDetailsSheet from '@/components/objects/ObjectDetailsSheet.vue';
import TransferDialog from '@/components/objects/TransferDialog.vue';
import UploadQueue from '@/components/objects/UploadQueue.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useUploads } from '@/composables/useUploads';
import { formatBytes, formatDateTime } from '@/lib/format';
import { getJson, requestJson } from '@/lib/http';
import { index, show as showStorage } from '@/routes/storages';
import type {
    ObjectCapabilities,
    ObjectItem,
    ObjectList,
    ObjectPermissions,
    Operation,
    Storage,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

const props = defineProps<{
    storage: Storage;
    bucket: string;
    prefix: string;
    search: string;
    listing: ObjectList;
    operations: Operation[];
    buckets?: string[];
    partSize: number;
    maxLinkSeconds: number;
    can: ObjectPermissions;
    capabilities: ObjectCapabilities;
}>();

const locale = usePage().props.locale;
const args = { storage: props.storage.id, bucket: props.bucket };

// Список: первая страница приходит с сервером, следующие подгружаются кнопкой
const folders = ref<string[]>([]);
const objects = ref<ObjectItem[]>([]);
const next = ref<string | null>(null);
const loadingMore = ref(false);
const selectedObjects = ref(new Set<string>());
const selectedFolders = ref(new Set<string>());

watch(
    () => props.listing,
    (listing) => {
        folders.value = [...listing.folders];
        objects.value = [...listing.objects];
        next.value = listing.next;
        selectedObjects.value = new Set();
        selectedFolders.value = new Set();
    },
    { immediate: true },
);

const visit = (prefix: string, search = '') =>
    ObjectController.index.url(args, {
        query: { prefix: prefix || null, search: search || null },
    });

const refresh = useThrottleFn(
    () => router.reload({ only: ['listing', 'operations'] }),
    500,
    true,
);

const refreshOperations = useThrottleFn(
    () => router.reload({ only: ['operations'] }),
    500,
    true,
);

// Окончание фоновой операции над папкой меняет содержимое — перечитываем список
useEcho<{ operation: Operation }>(
    `storages.${props.storage.id}`,
    '.operation.updated',
    (event) => {
        if (event.operation.bucket !== props.bucket) {
            return;
        }

        if (event.operation.status === 'completed') {
            void refresh();
        } else {
            void refreshOperations();
        }
    },
);

async function loadMore(): Promise<void> {
    if (next.value === null) {
        return;
    }

    loadingMore.value = true;

    try {
        const page = await getJson<ObjectList>(
            ObjectController.list.url(args, {
                query: {
                    prefix: props.prefix || null,
                    search: props.search || null,
                    token: next.value,
                },
            }),
        );

        if (page.error) {
            toast.error(page.error);

            return;
        }

        folders.value.push(...page.folders);
        objects.value.push(...page.objects);
        next.value = page.next;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loadingMore.value = false;
    }
}

// Поиск по началу имени внутри текущей папки
const searchInput = ref(props.search);
const applySearch = useDebounceFn((value: string) => {
    router.get(
        visit(props.prefix, value),
        {},
        { preserveState: true, replace: true, only: ['listing', 'search'] },
    );
}, 400);
watch(searchInput, (value) => void applySearch(value));

const segments = computed(() => {
    const parts = props.prefix.split('/').filter(Boolean);

    return parts.map((name, i) => ({
        name,
        prefix: parts.slice(0, i + 1).join('/') + '/',
    }));
});

const folderName = (prefix: string) =>
    prefix.slice(props.prefix.length).replace(/\/$/, '');

const downloadUrl = (key: string) =>
    ObjectController.download.url(args, { query: { key } });

const operationsByPrefix = computed(() => {
    const map = new Map<string, Operation>();

    for (const operation of props.operations) {
        if (
            operation.prefix &&
            (operation.status === 'queued' || operation.status === 'running')
        ) {
            map.set(operation.prefix, operation);
        }
    }

    return map;
});
const failedOperations = computed(() =>
    props.operations.filter((operation) => operation.status === 'failed'),
);
const runningOperations = computed(() =>
    props.operations.filter(
        (operation) =>
            operation.status === 'queued' || operation.status === 'running',
    ),
);

// Выбор
const selectedCount = computed(
    () => selectedObjects.value.size + selectedFolders.value.size,
);
const allSelected = computed(
    () =>
        selectedCount.value > 0 &&
        selectedCount.value === objects.value.length + folders.value.length,
);

function toggleAll(value: boolean): void {
    selectedObjects.value = new Set(
        value ? objects.value.map((object) => object.key) : [],
    );
    selectedFolders.value = new Set(value ? folders.value : []);
}

function toggle(set: Set<string>, value: string, checked: boolean): void {
    if (checked) {
        set.add(value);
    } else {
        set.delete(value);
    }
}

async function deleteSelected(): Promise<void> {
    try {
        const messages: string[] = [];
        const keys = [...selectedObjects.value];

        for (let i = 0; i < keys.length; i += 1000) {
            const { message } = await requestJson<{ message: string }>(
                'DELETE',
                ObjectManageController.destroy.url(args),
                { objects: keys.slice(i, i + 1000).map((key) => ({ key })) },
            );
            messages.push(message);
        }

        for (const prefix of selectedFolders.value) {
            const { message } = await requestJson<{ message: string }>(
                'POST',
                ObjectManageController.folderOperation.url(args),
                { type: 'delete', prefix },
            );
            messages.push(message);
        }

        messages.forEach((message) => toast.success(message));
        toggleAll(false);
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        void refresh();
    }
}

// Копирование и перемещение
const transferOpen = ref(false);
const transferMove = ref(false);

function openTransfer(move: boolean): void {
    transferMove.value = move;
    transferOpen.value = true;
}

async function transfer(
    targetBucket: string,
    targetPrefix: string,
): Promise<void> {
    const keys = [...selectedObjects.value];

    for (let i = 0; i < keys.length; i += 100) {
        const { message } = await requestJson<{ message: string }>(
            'POST',
            ObjectManageController.transfer.url(args),
            {
                keys: keys.slice(i, i + 100),
                source_prefix: props.prefix,
                target_bucket: targetBucket,
                target_prefix: targetPrefix,
                move: transferMove.value,
            },
        );
        toast.success(message);
    }

    for (const prefix of selectedFolders.value) {
        const { message } = await requestJson<{ message: string }>(
            'POST',
            ObjectManageController.folderOperation.url(args),
            {
                type: transferMove.value ? 'move' : 'copy',
                prefix,
                target_bucket: targetBucket,
                target_prefix: targetPrefix,
            },
        );
        toast.success(message);
    }

    toggleAll(false);
    void refresh();
}

// Загрузка
const uploads = useUploads(props.storage.id, props.bucket, props.partSize, () =>
    refresh(),
);
const fileInput = ref<HTMLInputElement | null>(null);
const folderInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

function addFiles(files: { file: File; path: string }[]): void {
    uploads.add(
        files.map(({ file, path }) => ({ file, key: props.prefix + path })),
    );
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    addFiles(
        [...(input.files ?? [])].map((file) => ({
            file,
            path: file.webkitRelativePath || file.name,
        })),
    );
    input.value = '';
}

/** Папки при перетаскивании обходятся рекурсивно: ключи сохраняют относительный путь */
async function readEntry(
    entry: FileSystemEntry,
    path: string,
): Promise<{ file: File; path: string }[]> {
    if (entry.isFile) {
        const file = await new Promise<File>((resolve, reject) =>
            (entry as FileSystemFileEntry).file(resolve, reject),
        );

        return [{ file, path: path + file.name }];
    }

    const reader = (entry as FileSystemDirectoryEntry).createReader();
    const result: { file: File; path: string }[] = [];
    let batch: FileSystemEntry[];

    do {
        batch = await new Promise<FileSystemEntry[]>((resolve, reject) =>
            reader.readEntries(resolve, reject),
        );

        for (const child of batch) {
            result.push(...(await readEntry(child, `${path}${entry.name}/`)));
        }
    } while (batch.length > 0);

    return result;
}

async function onDrop(event: DragEvent): Promise<void> {
    dragging.value = false;

    if (!props.can.put || !event.dataTransfer) {
        return;
    }

    const entries = [...event.dataTransfer.items]
        .map((item) => item.webkitGetAsEntry())
        .filter((entry): entry is FileSystemEntry => entry !== null);

    const files: { file: File; path: string }[] = [];

    for (const entry of entries) {
        files.push(...(await readEntry(entry, '')));
    }

    addFiles(files);
}

function onDragOver(event: DragEvent): void {
    if (props.can.put && event.dataTransfer?.types.includes('Files')) {
        dragging.value = true;
    }
}

// Свойства объекта
const openedKey = ref<string | null>(null);

const bulkVerb = computed(() =>
    trans('Selected: :count', { count: String(selectedCount.value) }),
);
</script>

<template>
    <Head :title="bucket" />

    <div
        class="relative flex flex-col gap-4 p-4"
        @dragover.prevent="onDragOver"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
    >
        <div
            v-if="dragging"
            class="pointer-events-none absolute inset-2 z-30 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-primary/5 text-sm font-medium"
        >
            {{ $t('Drop files or folders to upload') }}
        </div>

        <div class="flex flex-col gap-2">
            <Link
                :href="showStorage(storage.id)"
                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ storage.name }}
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading :title="bucket" :description="storage.providerLabel" />
                <Button
                    v-if="can.bucketSettings"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link
                        :href="
                            BucketSettingsController.show.url({
                                storage: storage.id,
                                bucket,
                            })
                        "
                        data-test="bucket-settings"
                    >
                        <Settings />
                        {{ $t('Bucket settings') }}
                    </Link>
                </Button>
            </div>
        </div>

        <nav
            class="flex flex-wrap items-center gap-1 font-mono text-sm"
            data-test="path"
        >
            <Link
                :href="visit('')"
                class="underline-offset-4 hover:underline"
                >{{ bucket }}</Link
            >
            <template v-for="segment in segments" :key="segment.prefix">
                <ChevronRight class="size-4 text-muted-foreground" />
                <Link
                    :href="visit(segment.prefix)"
                    class="underline-offset-4 hover:underline"
                    >{{ segment.name }}</Link
                >
            </template>
        </nav>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-64">
                <Search
                    class="absolute top-2.5 left-2 size-4 text-muted-foreground"
                />
                <Input
                    v-model="searchInput"
                    class="pl-8"
                    :placeholder="$t('Name starts with…')"
                    data-test="object-search"
                />
            </div>
            <Button
                variant="ghost"
                size="icon"
                :aria-label="$t('Refresh')"
                @click="refresh()"
            >
                <RefreshCw />
            </Button>
            <div class="flex-1" />
            <MultipartUploadsDialog
                v-if="capabilities.uploads"
                :storage="storage.id"
                :bucket="bucket"
                :can-abort="can.delete"
            />
            <template v-if="can.put">
                <NewFolderDialog
                    :storage="storage.id"
                    :bucket="bucket"
                    :prefix="prefix"
                    @created="refresh()"
                />
                <Button variant="outline" @click="folderInput?.click()">
                    <FolderInput />
                    {{ $t('Upload folder') }}
                </Button>
                <Button data-test="upload-files" @click="fileInput?.click()">
                    <Upload />
                    {{ $t('Upload files') }}
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    data-test="file-input"
                    @change="onPick"
                />
                <input
                    ref="folderInput"
                    type="file"
                    webkitdirectory
                    class="hidden"
                    @change="onPick"
                />
            </template>
        </div>

        <div
            v-for="operation in runningOperations"
            :key="operation.id"
            class="flex items-center gap-2 rounded-lg border p-3 text-sm"
            data-test="folder-operation"
        >
            <Spinner class="size-4" />
            {{ operation.typeLabel }}
            <span class="font-mono">{{ operation.prefix }}</span
            >:
            {{
                $t(':count objects processed', {
                    count: String(operation.processed),
                })
            }}
        </div>
        <div
            v-for="operation in failedOperations"
            :key="operation.id"
            class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-3 text-sm"
        >
            <CircleX class="size-5 shrink-0 text-red-600" />
            <div>
                <p class="font-medium">
                    {{ operation.typeLabel }}
                    <span class="font-mono">{{ operation.prefix }}</span
                    >: {{ operation.statusLabel }}
                </p>
                <p class="text-muted-foreground">{{ operation.error }}</p>
            </div>
        </div>

        <div
            v-if="selectedCount > 0"
            class="sticky top-0 z-20 flex flex-wrap items-center gap-2 rounded-lg border bg-background p-2 text-sm shadow-sm"
            data-test="bulk-bar"
        >
            <span class="px-2">{{ bulkVerb }}</span>
            <template v-if="can.put">
                <Button
                    variant="outline"
                    size="sm"
                    @click="openTransfer(false)"
                >
                    <Copy />
                    {{ $t('Copy') }}
                </Button>
                <Button
                    v-if="can.delete"
                    variant="outline"
                    size="sm"
                    data-test="bulk-move"
                    @click="openTransfer(true)"
                >
                    <FolderInput />
                    {{ $t('Move') }}
                </Button>
            </template>
            <ConfirmDialog
                v-if="can.delete"
                :title="trans('Delete selected?')"
                :description="
                    trans(
                        'Objects: :objects, folders with all their contents: :folders. In a versioned bucket delete markers are created and data can be restored.',
                        {
                            objects: String(selectedObjects.size),
                            folders: String(selectedFolders.size),
                        },
                    )
                "
                :confirm-label="trans('Delete')"
                variant="destructive"
                :on-confirm="deleteSelected"
            >
                <Button
                    variant="outline"
                    size="sm"
                    class="text-destructive"
                    data-test="bulk-delete"
                >
                    <Trash2 />
                    {{ $t('Delete') }}
                </Button>
            </ConfirmDialog>
            <Button variant="ghost" size="sm" @click="toggleAll(false)">{{
                $t('Clear selection')
            }}</Button>
        </div>

        <div
            v-if="listing.error"
            class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-4 text-sm"
        >
            <CircleX class="size-5 shrink-0 text-red-600" />
            <p>{{ listing.error }}</p>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="w-10 px-3 py-2">
                            <Checkbox
                                :model-value="allSelected"
                                :aria-label="$t('Select all')"
                                @update:model-value="
                                    (value) => toggleAll(value === true)
                                "
                            />
                        </th>
                        <th class="px-3 py-2 font-medium">{{ $t('Name') }}</th>
                        <th class="px-3 py-2 font-medium">{{ $t('Size') }}</th>
                        <th class="px-3 py-2 font-medium">
                            {{ $t('Modified') }}
                        </th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="folder in folders"
                        :key="folder"
                        class="border-t"
                        data-test="folder-row"
                    >
                        <td class="px-3 py-2">
                            <Checkbox
                                :model-value="selectedFolders.has(folder)"
                                :disabled="operationsByPrefix.has(folder)"
                                :aria-label="folderName(folder)"
                                @update:model-value="
                                    (value) =>
                                        toggle(
                                            selectedFolders,
                                            folder,
                                            value === true,
                                        )
                                "
                            />
                        </td>
                        <td class="px-3 py-2" colspan="3">
                            <Link
                                :href="visit(folder)"
                                class="flex items-center gap-2 font-mono underline-offset-4 hover:underline"
                            >
                                <Folder class="size-4 text-amber-500" />
                                {{ folderName(folder) }}/
                                <Spinner
                                    v-if="operationsByPrefix.has(folder)"
                                    class="size-3"
                                />
                            </Link>
                        </td>
                        <td />
                    </tr>
                    <tr
                        v-for="object in objects"
                        :key="object.key"
                        class="border-t"
                        data-test="object-row"
                    >
                        <td class="px-3 py-2">
                            <Checkbox
                                :model-value="selectedObjects.has(object.key)"
                                :aria-label="object.name"
                                @update:model-value="
                                    (value) =>
                                        toggle(
                                            selectedObjects,
                                            object.key,
                                            value === true,
                                        )
                                "
                            />
                        </td>
                        <td class="px-3 py-2">
                            <button
                                type="button"
                                class="flex items-center gap-2 text-left font-mono break-all underline-offset-4 hover:underline"
                                :data-test="`open-${object.name}`"
                                @click="openedKey = object.key"
                            >
                                <FileIcon
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                {{ object.name }}
                            </button>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ formatBytes(object.size, locale) }}
                        </td>
                        <td
                            class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDateTime(object.lastModified, locale) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button
                                as-child
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Download')"
                            >
                                <a :href="downloadUrl(object.key)">
                                    <Download />
                                </a>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="folders.length === 0 && objects.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-8 text-center text-muted-foreground"
                        >
                            {{
                                search
                                    ? $t('Nothing found.')
                                    : can.put
                                      ? $t(
                                            'The folder is empty. Drag files here to upload them.',
                                        )
                                      : $t('The folder is empty.')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Button
            v-if="next"
            variant="outline"
            class="self-center"
            :disabled="loadingMore"
            data-test="load-more"
            @click="loadMore"
        >
            <Spinner v-if="loadingMore" />
            {{ $t('Load more') }}
        </Button>

        <TransferDialog
            v-model:open="transferOpen"
            :bucket="bucket"
            :prefix="prefix"
            :move="transferMove"
            :count="selectedCount"
            :buckets="buckets"
            :on-submit="transfer"
        />

        <ObjectDetailsSheet
            v-model:object-key="openedKey"
            :storage="storage.id"
            :bucket="bucket"
            :can="can"
            :capabilities="capabilities"
            :max-link-seconds="maxLinkSeconds"
            @changed="refresh()"
        />

        <UploadQueue
            :items="uploads.items"
            :loaded-bytes="uploads.loadedBytes.value"
            :total-bytes="uploads.totalBytes.value"
            @cancel="uploads.cancel"
            @retry="uploads.retry"
            @clear="uploads.clearFinished"
        />
    </div>
</template>
