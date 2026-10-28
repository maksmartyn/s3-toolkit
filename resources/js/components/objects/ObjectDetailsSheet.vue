<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Copy, Download, Plus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { toast } from 'vue-sonner';
import { computed, reactive, ref, watch } from 'vue';
import ObjectController from '@/actions/App/Http/Controllers/Bucket/ObjectController';
import ObjectManageController from '@/actions/App/Http/Controllers/Bucket/ObjectManageController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { formatBytes, formatDateTime } from '@/lib/format';
import { getJson, HttpValidationError, requestJson } from '@/lib/http';
import type {
    BucketAcl,
    BucketSection,
    CannedAcl,
    ObjectCapabilities,
    ObjectDetails,
    ObjectPermissions,
    ObjectProtection,
    ObjectVersion,
    RetentionMode,
} from '@/types';

const props = defineProps<{
    storage: number;
    bucket: string;
    can: ObjectPermissions;
    capabilities: ObjectCapabilities;
    maxLinkSeconds: number;
}>();

/** Ключ открытого объекта; null — панель закрыта */
const objectKey = defineModel<string | null>('objectKey', { required: true });

const emit = defineEmits<{ changed: [] }>();

const locale = usePage().props.locale;
const args = () => ({ storage: props.storage, bucket: props.bucket });

type Tab = 'properties' | 'tags' | 'versions' | 'acl' | 'protection' | 'link';
const tab = ref<Tab>('properties');

const tabs = computed(() =>
    (
        [
            { value: 'properties', label: 'Properties', available: true },
            {
                value: 'tags',
                label: 'Tags',
                available: props.capabilities.tagging,
            },
            {
                value: 'versions',
                label: 'Versions',
                available: props.capabilities.versioning,
            },
            { value: 'acl', label: 'ACL', available: props.capabilities.acl },
            {
                value: 'protection',
                label: 'Protection',
                available: props.capabilities.objectLock,
            },
            { value: 'link', label: 'Link', available: props.can.share },
        ] as { value: Tab; label: string; available: boolean }[]
    ).filter((item) => item.available),
);

const details = ref<ObjectDetails | null>(null);
const loadError = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const metadata = reactive({
    content_type: '',
    cache_control: '',
    content_disposition: '',
    metadata: [] as { key: string; value: string }[],
});
const tags = ref<{ key: string; value: string }[]>([]);
const newKey = ref('');

async function loadDetails(): Promise<void> {
    if (objectKey.value === null) {
        return;
    }

    loadError.value = null;

    try {
        const data = await getJson<ObjectDetails>(
            ObjectController.details.url(args(), {
                query: { key: objectKey.value },
            }),
        );
        details.value = data;
        metadata.content_type = data.contentType ?? '';
        metadata.cache_control = data.cacheControl ?? '';
        metadata.content_disposition = data.contentDisposition ?? '';
        metadata.metadata = Object.entries(data.metadata).map(
            ([key, value]) => ({ key, value }),
        );
        tags.value = (data.tags ?? []).map((tag) => ({ ...tag }));
        newKey.value = data.key;
    } catch (e) {
        loadError.value = (e as Error).message;
    }
}

/**
 * Изменение: ответ сервера показываем всплывающим сообщением, ошибки полей — под полями.
 */
async function change(
    method: 'POST' | 'PUT' | 'DELETE',
    url: string,
    body: Record<string, unknown>,
    after?: () => Promise<void> | void,
): Promise<boolean> {
    errors.value = {};
    processing.value = true;

    try {
        const { message } = await requestJson<{ message: string }>(
            method,
            url,
            body,
        );
        toast.success(message);
        await after?.();

        return true;
    } catch (e) {
        if (e instanceof HttpValidationError) {
            errors.value = e.errors;
        } else {
            toast.error((e as Error).message);
        }

        return false;
    } finally {
        processing.value = false;
    }
}

const downloadUrl = (version: string | null = null) =>
    ObjectController.download.url(args(), {
        query: { key: objectKey.value ?? '', version },
    });

function saveMetadata(): void {
    void change(
        'PUT',
        ObjectManageController.metadata.url(args()),
        { key: objectKey.value, ...metadata },
        loadDetails,
    );
}

function saveTags(): void {
    void change(
        'PUT',
        ObjectManageController.tags.url(args()),
        { key: objectKey.value, tags: tags.value },
        loadDetails,
    );
}

async function rename(): Promise<void> {
    const target = newKey.value;

    if (
        await change('POST', ObjectManageController.rename.url(args()), {
            key: objectKey.value,
            new_key: target,
        })
    ) {
        emit('changed');
        objectKey.value = target;
    }
}

async function remove(): Promise<void> {
    if (
        await change('DELETE', ObjectManageController.destroy.url(args()), {
            objects: [{ key: objectKey.value }],
        })
    ) {
        emit('changed');
        objectKey.value = null;
    }
}

// Версии
const versions = ref<ObjectVersion[] | null>(null);
const versionsError = ref<string | null>(null);

async function loadVersions(): Promise<void> {
    versionsError.value = null;

    try {
        versions.value = await getJson<ObjectVersion[]>(
            ObjectController.versions.url(args(), {
                query: { key: objectKey.value ?? '' },
            }),
        );
    } catch (e) {
        versionsError.value = (e as Error).message;
    }
}

function restoreVersion(version: ObjectVersion): Promise<boolean> {
    return change(
        'POST',
        ObjectManageController.restore.url(args()),
        { key: objectKey.value, version: version.versionId },
        async () => {
            emit('changed');
            await Promise.all([loadVersions(), loadDetails()]);
        },
    );
}

function deleteVersion(version: ObjectVersion): Promise<boolean> {
    return change(
        'DELETE',
        ObjectManageController.destroy.url(args()),
        { objects: [{ key: objectKey.value, version: version.versionId }] },
        async () => {
            emit('changed');
            await loadVersions();
        },
    );
}

// ACL
const acl = ref<BucketSection<BucketAcl> | null>(null);
const selectedAcl = ref<CannedAcl | null>(null);
const aclOptions: { value: CannedAcl; label: string }[] = [
    { value: 'private', label: 'Private' },
    { value: 'public-read', label: 'Public read' },
    { value: 'public-read-write', label: 'Public read and write' },
    { value: 'authenticated-read', label: 'Read for any authenticated user' },
];

async function loadAcl(): Promise<void> {
    acl.value = await getJson<BucketSection<BucketAcl>>(
        ObjectController.acl.url(args(), {
            query: { key: objectKey.value ?? '' },
        }),
    ).catch((e: Error) => ({ data: null as never, error: e.message }));
    selectedAcl.value = acl.value.data?.canned ?? null;
}

function saveAcl(): void {
    void change(
        'PUT',
        ObjectManageController.acl.url(args()),
        { key: objectKey.value, acl: selectedAcl.value },
        loadAcl,
    );
}

// Защита
const protection = ref<BucketSection<ObjectProtection> | null>(null);
const retention = reactive({
    mode: 'GOVERNANCE' as RetentionMode,
    until: '',
});

async function loadProtection(): Promise<void> {
    protection.value = await getJson<BucketSection<ObjectProtection>>(
        ObjectController.protection.url(args(), {
            query: { key: objectKey.value ?? '' },
        }),
    ).catch((e: Error) => ({ data: null as never, error: e.message }));
    retention.mode = protection.value.data?.mode ?? 'GOVERNANCE';
}

function saveRetention(): void {
    void change(
        'PUT',
        ObjectManageController.retention.url(args()),
        {
            key: objectKey.value,
            version: details.value?.versionId,
            mode: retention.mode,
            until: retention.until
                ? new Date(retention.until).toISOString()
                : '',
        },
        loadProtection,
    );
}

function toggleLegalHold(on: boolean): void {
    void change(
        'PUT',
        ObjectManageController.legalHold.url(args()),
        { key: objectKey.value, version: details.value?.versionId, on },
        loadProtection,
    );
}

// Ссылка
const linkOptions = [
    { seconds: 3600, label: '1 hour' },
    { seconds: 86400, label: '1 day' },
    { seconds: 604800, label: '7 days' },
];
const linkSeconds = ref(3600);
const linkAttachment = ref(false);
const link = ref<{ url: string; expiresAt: string } | null>(null);

async function createLink(): Promise<void> {
    processing.value = true;

    try {
        link.value = await requestJson<{ url: string; expiresAt: string }>(
            'POST',
            ObjectController.share.url(args()),
            {
                key: objectKey.value,
                expires: linkSeconds.value,
                attachment: linkAttachment.value,
            },
        );
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        processing.value = false;
    }
}

async function copyLink(): Promise<void> {
    if (link.value) {
        await navigator.clipboard.writeText(link.value.url);
        toast.success(trans('Link copied.'));
    }
}

watch(
    objectKey,
    (key) => {
        details.value = null;
        versions.value = null;
        acl.value = null;
        protection.value = null;
        link.value = null;
        errors.value = {};
        tab.value = 'properties';

        if (key !== null) {
            void loadDetails();
        }
    },
    { immediate: true },
);

watch(tab, (value) => {
    if (value === 'versions' && versions.value === null) {
        void loadVersions();
    }

    if (value === 'acl' && acl.value === null) {
        void loadAcl();
    }

    if (value === 'protection' && protection.value === null) {
        void loadProtection();
    }
});

const open = computed({
    get: () => objectKey.value !== null,
    set: (value) => {
        if (!value) {
            objectKey.value = null;
        }
    },
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            class="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-xl"
            data-test="object-details"
        >
            <SheetHeader>
                <SheetTitle class="pr-6 font-mono break-all">{{
                    objectKey
                }}</SheetTitle>
                <SheetDescription>{{ bucket }}</SheetDescription>
            </SheetHeader>

            <div class="flex flex-col gap-4 px-4 pb-4">
                <nav class="flex flex-wrap gap-1 border-b" role="tablist">
                    <button
                        v-for="item in tabs"
                        :key="item.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.value"
                        class="-mb-px border-b-2 px-2 py-1.5 text-sm"
                        :class="
                            tab === item.value
                                ? 'border-primary font-medium'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        :data-test="`object-tab-${item.value}`"
                        @click="tab = item.value"
                    >
                        {{ $t(item.label) }}
                    </button>
                </nav>

                <p v-if="loadError" class="text-sm text-destructive">
                    {{ loadError }}
                </p>
                <Spinner v-else-if="details === null" />

                <template v-else-if="tab === 'properties'">
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm"
                    >
                        <dt class="text-muted-foreground">{{ $t('Size') }}</dt>
                        <dd>{{ formatBytes(details.size, locale) }}</dd>
                        <dt class="text-muted-foreground">
                            {{ $t('Modified') }}
                        </dt>
                        <dd>
                            {{ formatDateTime(details.lastModified, locale) }}
                        </dd>
                        <template v-if="details.storageClass">
                            <dt class="text-muted-foreground">
                                {{ $t('Storage class') }}
                            </dt>
                            <dd class="font-mono">
                                {{ details.storageClass }}
                            </dd>
                        </template>
                        <dt class="text-muted-foreground">ETag</dt>
                        <dd class="font-mono break-all">{{ details.etag }}</dd>
                        <template v-if="details.versionId">
                            <dt class="text-muted-foreground">
                                {{ $t('Version') }}
                            </dt>
                            <dd class="font-mono break-all">
                                {{ details.versionId }}
                            </dd>
                        </template>
                    </dl>

                    <Button as-child variant="outline" class="self-start">
                        <a :href="downloadUrl()" data-test="details-download">
                            <Download />
                            {{ $t('Download') }}
                        </a>
                    </Button>

                    <form
                        class="flex flex-col gap-3 border-t pt-4"
                        @submit.prevent="saveMetadata"
                    >
                        <h3 class="text-sm font-medium">
                            {{ $t('Metadata') }}
                        </h3>
                        <div class="grid gap-1">
                            <Label for="content-type">Content-Type</Label>
                            <Input
                                id="content-type"
                                v-model="metadata.content_type"
                                class="font-mono"
                                :disabled="!can.put"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="cache-control">Cache-Control</Label>
                            <Input
                                id="cache-control"
                                v-model="metadata.cache_control"
                                class="font-mono"
                                :disabled="!can.put"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label for="content-disposition"
                                >Content-Disposition</Label
                            >
                            <Input
                                id="content-disposition"
                                v-model="metadata.content_disposition"
                                class="font-mono"
                                :disabled="!can.put"
                            />
                        </div>
                        <Label>{{ $t('User metadata (x-amz-meta-*)') }}</Label>
                        <div
                            v-for="(item, index) in metadata.metadata"
                            :key="index"
                            class="flex gap-2"
                        >
                            <Input
                                v-model="item.key"
                                class="font-mono"
                                :aria-label="$t('Key')"
                                :disabled="!can.put"
                            />
                            <Input
                                v-model="item.value"
                                class="font-mono"
                                :aria-label="$t('Value')"
                                :disabled="!can.put"
                            />
                            <Button
                                v-if="can.put"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Remove')"
                                @click="metadata.metadata.splice(index, 1)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('metadata'),
                                )?.[1]
                            "
                        />
                        <div v-if="can.put" class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    metadata.metadata.push({
                                        key: '',
                                        value: '',
                                    })
                                "
                            >
                                <Plus />
                                {{ $t('Add') }}
                            </Button>
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="save-metadata"
                            >
                                {{ $t('Save') }}
                            </Button>
                        </div>
                        <p v-if="can.put" class="text-xs text-muted-foreground">
                            {{
                                $t(
                                    'S3 cannot change metadata in place: the object is copied onto itself. In a versioned bucket this creates a new version.',
                                )
                            }}
                        </p>
                    </form>

                    <form
                        v-if="can.put && can.delete"
                        class="flex flex-col gap-2 border-t pt-4"
                        @submit.prevent="rename"
                    >
                        <Label for="new-key">{{ $t('Rename or move') }}</Label>
                        <div class="flex gap-2">
                            <Input
                                id="new-key"
                                v-model="newKey"
                                class="font-mono"
                                data-test="rename-key"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing || newKey === objectKey"
                                data-test="rename-submit"
                                >{{ $t('Rename') }}</Button
                            >
                        </div>
                        <InputError :message="errors.new_key" />
                    </form>

                    <div v-if="can.delete" class="border-t pt-4">
                        <ConfirmDialog
                            :title="trans('Delete object?')"
                            :description="
                                capabilities.versioning
                                    ? trans(
                                          'If versioning is enabled, a delete marker is created and the object can be restored.',
                                      )
                                    : undefined
                            "
                            :confirm-label="trans('Delete')"
                            variant="destructive"
                            :on-confirm="remove"
                        >
                            <Button
                                variant="ghost"
                                class="text-destructive"
                                data-test="details-delete"
                                >{{ $t('Delete') }}</Button
                            >
                        </ConfirmDialog>
                    </div>
                </template>

                <template v-else-if="tab === 'tags'">
                    <p
                        v-if="details.tags === null"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('Tags are not available for this object.') }}
                    </p>
                    <form
                        v-else
                        class="flex flex-col gap-2"
                        @submit.prevent="saveTags"
                    >
                        <p
                            v-if="tags.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ $t('No tags.') }}
                        </p>
                        <div
                            v-for="(tag, index) in tags"
                            :key="index"
                            class="flex gap-2"
                        >
                            <Input
                                v-model="tag.key"
                                class="font-mono"
                                :aria-label="$t('Key')"
                                :disabled="!can.put"
                            />
                            <Input
                                v-model="tag.value"
                                class="font-mono"
                                :aria-label="$t('Value')"
                                :disabled="!can.put"
                            />
                            <Button
                                v-if="can.put"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="$t('Remove')"
                                @click="tags.splice(index, 1)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('tags'),
                                )?.[1]
                            "
                        />
                        <div v-if="can.put" class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="tags.length >= 10"
                                @click="tags.push({ key: '', value: '' })"
                            >
                                <Plus />
                                {{ $t('Add tag') }}
                            </Button>
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="save-object-tags"
                                >{{ $t('Save') }}</Button
                            >
                        </div>
                    </form>
                </template>

                <template v-else-if="tab === 'versions'">
                    <p v-if="versionsError" class="text-sm text-destructive">
                        {{ versionsError }}
                    </p>
                    <Spinner v-else-if="versions === null" />
                    <div v-else class="flex flex-col gap-2">
                        <div
                            v-for="version in versions"
                            :key="version.versionId ?? 'null'"
                            class="flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm"
                            data-test="object-version"
                        >
                            <div class="min-w-0">
                                <p class="flex items-center gap-2">
                                    <span class="truncate font-mono text-xs">{{
                                        version.versionId ?? 'null'
                                    }}</span>
                                    <Badge v-if="version.isLatest">{{
                                        $t('Current')
                                    }}</Badge>
                                    <Badge
                                        v-if="version.isDeleteMarker"
                                        variant="secondary"
                                        >{{ $t('Delete marker') }}</Badge
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        formatDateTime(
                                            version.lastModified,
                                            locale,
                                        )
                                    }}
                                    <template v-if="!version.isDeleteMarker"
                                        >·
                                        {{
                                            formatBytes(version.size, locale)
                                        }}</template
                                    >
                                </p>
                            </div>
                            <div class="flex gap-1">
                                <Button
                                    v-if="!version.isDeleteMarker"
                                    as-child
                                    variant="ghost"
                                    size="sm"
                                >
                                    <a :href="downloadUrl(version.versionId)">{{
                                        $t('Download')
                                    }}</a>
                                </Button>
                                <ConfirmDialog
                                    v-if="
                                        can.put &&
                                        !version.isLatest &&
                                        !version.isDeleteMarker
                                    "
                                    :title="trans('Restore this version?')"
                                    :description="
                                        trans(
                                            'A copy of this version becomes the current version. Other versions are kept.',
                                        )
                                    "
                                    :confirm-label="trans('Restore')"
                                    :on-confirm="() => restoreVersion(version)"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="restore-version"
                                        >{{ $t('Restore') }}</Button
                                    >
                                </ConfirmDialog>
                                <ConfirmDialog
                                    v-if="can.delete"
                                    :title="
                                        trans(
                                            'Delete this version permanently?',
                                        )
                                    "
                                    :description="
                                        trans(
                                            'The version is deleted for good and cannot be restored.',
                                        )
                                    "
                                    :confirm-label="trans('Delete')"
                                    variant="destructive"
                                    :on-confirm="() => deleteVersion(version)"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        >{{ $t('Delete') }}</Button
                                    >
                                </ConfirmDialog>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="tab === 'acl'">
                    <Spinner v-if="acl === null" />
                    <p v-else-if="acl.error" class="text-sm text-destructive">
                        {{ acl.error }}
                    </p>
                    <form
                        v-else
                        class="flex flex-col gap-2"
                        @submit.prevent="saveAcl"
                    >
                        <p
                            v-if="acl.data.canned === null"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                $t(
                                    'The current grants were configured manually. Choosing a preset will replace them.',
                                )
                            }}
                        </p>
                        <label
                            v-for="option in aclOptions"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="selectedAcl"
                                type="radio"
                                :value="option.value"
                                :disabled="!can.manageAcl"
                            />
                            {{ $t(option.label) }}
                        </label>
                        <Button
                            v-if="can.manageAcl"
                            type="submit"
                            class="self-start"
                            :disabled="
                                processing ||
                                selectedAcl === null ||
                                selectedAcl === acl.data.canned
                            "
                            >{{ $t('Save') }}</Button
                        >
                    </form>
                </template>

                <template v-else-if="tab === 'protection'">
                    <Spinner v-if="protection === null" />
                    <p
                        v-else-if="protection.error"
                        class="text-sm text-destructive"
                    >
                        {{ protection.error }}
                    </p>
                    <div v-else class="flex flex-col gap-4 text-sm">
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                            <dt class="text-muted-foreground">
                                {{ $t('Retention') }}
                            </dt>
                            <dd>
                                {{
                                    protection.data.mode
                                        ? `${protection.data.mode}, ${$t('until :date', { date: formatDateTime(protection.data.until, locale) ?? '' })}`
                                        : $t('None')
                                }}
                            </dd>
                            <dt class="text-muted-foreground">
                                {{ $t('Legal hold') }}
                            </dt>
                            <dd>
                                {{
                                    protection.data.legalHold
                                        ? $t('On')
                                        : $t('Off')
                                }}
                            </dd>
                        </dl>

                        <form
                            v-if="can.manageObjectLock"
                            class="flex flex-col gap-2 border-t pt-4"
                            @submit.prevent="saveRetention"
                        >
                            <Label>{{ $t('Set retention') }}</Label>
                            <div class="flex flex-wrap gap-2">
                                <select
                                    v-model="retention.mode"
                                    class="rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                                    :aria-label="$t('Mode')"
                                >
                                    <option value="GOVERNANCE">
                                        {{ $t('Governance') }}
                                    </option>
                                    <option value="COMPLIANCE">
                                        {{ $t('Compliance') }}
                                    </option>
                                </select>
                                <Input
                                    v-model="retention.until"
                                    type="datetime-local"
                                    class="w-56"
                                    :aria-label="$t('Until')"
                                />
                                <Button type="submit" :disabled="processing">{{
                                    $t('Save')
                                }}</Button>
                            </div>
                            <InputError
                                :message="errors.until ?? errors.mode"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'In Compliance mode the period cannot be shortened or removed by anyone.',
                                    )
                                }}
                            </p>
                        </form>

                        <div
                            v-if="can.manageObjectLock"
                            class="flex items-center gap-2 border-t pt-4"
                        >
                            <Checkbox
                                :model-value="protection.data.legalHold"
                                :disabled="processing"
                                @update:model-value="
                                    (value) => toggleLegalHold(value === true)
                                "
                            />
                            {{
                                $t(
                                    'Legal hold: protect from deletion with no end date',
                                )
                            }}
                        </div>
                    </div>
                </template>

                <template v-else-if="tab === 'link'">
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Anyone with the link can download the object until it expires. The link cannot be revoked early, and its creation is recorded in the audit log.',
                            )
                        }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="linkSeconds"
                            class="rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                            :aria-label="$t('Valid for')"
                            data-test="link-expires"
                        >
                            <option
                                v-for="option in linkOptions.filter(
                                    (item) => item.seconds <= maxLinkSeconds,
                                )"
                                :key="option.seconds"
                                :value="option.seconds"
                            >
                                {{ $t(option.label) }}
                            </option>
                        </select>
                        <Label class="flex items-center gap-2 font-normal">
                            <Checkbox v-model="linkAttachment" />
                            {{ $t('Download as file') }}
                        </Label>
                        <Button
                            :disabled="processing"
                            data-test="create-link"
                            @click="createLink"
                        >
                            <Spinner v-if="processing" />
                            {{ $t('Create link') }}
                        </Button>
                    </div>
                    <div v-if="link" class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <Input
                                :model-value="link.url"
                                readonly
                                class="font-mono text-xs"
                                data-test="link-url"
                            />
                            <Button
                                variant="outline"
                                size="icon"
                                :aria-label="$t('Copy')"
                                @click="copyLink"
                            >
                                <Copy />
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t('Valid until :date', {
                                    date:
                                        formatDateTime(
                                            link.expiresAt,
                                            locale,
                                        ) ?? '',
                                })
                            }}
                        </p>
                    </div>
                </template>
            </div>
        </SheetContent>
    </Sheet>
</template>
