<script setup lang="ts">
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { CircleX, RefreshCw } from '@lucide/vue';
import { useThrottleFn } from '@vueuse/core';
import BucketController from '@/actions/App/Http/Controllers/Bucket/BucketController';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import CreateBucketDialog from '@/components/buckets/CreateBucketDialog.vue';
import DeleteBucketDialog from '@/components/buckets/DeleteBucketDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatBytes, formatDateTime, formatNumber } from '@/lib/format';
import type { Bucket, BucketList, Operation, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    buckets?: BucketList | null;
    recentOperations?: Operation[];
    can: { createBuckets: boolean; deleteBuckets: boolean };
}>();

const locale = usePage().props.locale;

// События прогресса приходят часто: перечитываем данные не чаще раза в полсекунды
const refresh = useThrottleFn(
    () => router.reload({ only: ['buckets', 'recentOperations'] }),
    500,
    true,
);

useEcho(`storages.${props.storage.id}`, '.operation.updated', () => refresh());

function startStats(bucket: Bucket): void {
    router.post(
        BucketController.stats.url({
            storage: props.storage.id,
            bucket: bucket.name,
        }),
        {},
        { preserveScroll: true, onSuccess: () => refresh() },
    );
}

function progress(operation: Operation): string {
    const processed = formatNumber(operation.processed, locale);

    return operation.total
        ? `${processed} / ${formatNumber(operation.total, locale)}`
        : processed;
}

const failed = (operations: Operation[] | undefined) =>
    (operations ?? []).filter((operation) => operation.status === 'failed');
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading variant="small" :title="$t('Buckets')" />
            <CreateBucketDialog v-if="can.createBuckets" :storage="storage" />
        </div>

        <Deferred data="buckets">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <Skeleton class="h-10 w-full" />
                    <Skeleton class="h-10 w-full" />
                </div>
            </template>

            <div
                v-if="buckets?.error"
                class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-4 text-sm"
            >
                <CircleX class="size-5 shrink-0 text-red-600" />
                <p>{{ buckets.error }}</p>
            </div>

            <div v-else class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Name') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Created') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Objects') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Size') }}
                            </th>
                            <th class="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="bucket in buckets?.buckets ?? []"
                            :key="bucket.name"
                            class="border-t align-top"
                            :data-test="`bucket-${bucket.name}`"
                        >
                            <td class="px-4 py-2 font-mono">
                                <Link
                                    :href="
                                        BucketSettingsController.show.url({
                                            storage: storage.id,
                                            bucket: bucket.name,
                                        })
                                    "
                                    class="underline-offset-4 hover:underline"
                                    >{{ bucket.name }}</Link
                                >
                                <div
                                    v-for="operation in bucket.operations"
                                    :key="operation.id"
                                    class="mt-1 flex items-center gap-2 font-sans text-xs text-muted-foreground"
                                    data-test="bucket-operation"
                                >
                                    <Spinner class="size-3" />
                                    {{ operation.typeLabel }}:
                                    {{ progress(operation) }}
                                </div>
                            </td>
                            <td
                                class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    formatDateTime(bucket.createdAt, locale) ??
                                    '—'
                                }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <template v-if="bucket.stats">
                                    {{
                                        formatNumber(
                                            bucket.stats.objectCount,
                                            locale,
                                        )
                                    }}
                                    <div
                                        v-if="
                                            bucket.stats.versionCount !== null
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('versions: :count', {
                                                count: formatNumber(
                                                    bucket.stats.versionCount,
                                                    locale,
                                                ),
                                            })
                                        }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <template v-if="bucket.stats">
                                    {{
                                        formatBytes(
                                            bucket.stats.totalSize,
                                            locale,
                                        )
                                    }}
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            formatDateTime(
                                                bucket.stats.calculatedAt,
                                                locale,
                                            )
                                        }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="bucket.operations.length > 0"
                                    :data-test="`stats-bucket-${bucket.name}`"
                                    @click="startStats(bucket)"
                                >
                                    <RefreshCw />
                                    {{ $t('Calculate') }}
                                </Button>
                                <DeleteBucketDialog
                                    v-if="can.deleteBuckets"
                                    :storage="storage"
                                    :bucket="bucket"
                                    :disabled="bucket.operations.length > 0"
                                />
                            </td>
                        </tr>
                        <tr v-if="(buckets?.buckets ?? []).length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No buckets yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Deferred>

        <div
            v-for="operation in failed(recentOperations)"
            :key="operation.id"
            class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-3 text-sm"
            data-test="failed-operation"
        >
            <CircleX class="size-5 shrink-0 text-red-600" />
            <div>
                <p class="font-medium">
                    {{ operation.typeLabel }} «{{ operation.bucket }}»:
                    {{ operation.statusLabel }}
                </p>
                <p class="text-muted-foreground">{{ operation.error }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ formatDateTime(operation.finishedAt, locale) }}
                </p>
            </div>
        </div>
    </section>
</template>
