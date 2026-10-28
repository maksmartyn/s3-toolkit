<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, CircleX, RotateCcw, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { UploadItem } from '@/composables/useUploads';
import { formatBytes } from '@/lib/format';

const props = defineProps<{
    items: UploadItem[];
    loadedBytes: number;
    totalBytes: number;
}>();

defineEmits<{
    cancel: [item: UploadItem];
    retry: [item: UploadItem];
    clear: [];
}>();

const locale = usePage().props.locale;

const percent = (item: UploadItem) =>
    item.file.size === 0
        ? 100
        : Math.round((item.loaded / item.file.size) * 100);

const running = computed(() =>
    props.items.some(
        (item) => item.status === 'queued' || item.status === 'uploading',
    ),
);
</script>

<template>
    <div
        v-if="items.length > 0"
        class="fixed right-4 bottom-4 z-40 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2 rounded-lg border bg-background p-3 shadow-lg"
        data-test="upload-queue"
    >
        <div class="flex items-center justify-between gap-2 text-sm">
            <span class="font-medium">
                {{
                    running
                        ? $t('Uploading: :loaded of :total', {
                              loaded: formatBytes(loadedBytes, locale),
                              total: formatBytes(totalBytes, locale),
                          })
                        : $t('Uploads finished')
                }}
            </span>
            <Button
                v-if="!running"
                variant="ghost"
                size="icon"
                :aria-label="$t('Close')"
                @click="$emit('clear')"
            >
                <X />
            </Button>
        </div>

        <div class="flex max-h-64 flex-col gap-2 overflow-y-auto">
            <div
                v-for="item in items"
                :key="item.id"
                class="flex flex-col gap-1 text-xs"
                :data-test="`upload-${item.status}`"
            >
                <div class="flex items-center gap-2">
                    <Spinner
                        v-if="item.status === 'uploading'"
                        class="size-3 shrink-0"
                    />
                    <Check
                        v-else-if="item.status === 'done'"
                        class="size-3 shrink-0 text-green-600"
                    />
                    <CircleX
                        v-else-if="item.status === 'failed'"
                        class="size-3 shrink-0 text-red-600"
                    />
                    <span class="min-w-0 flex-1 truncate font-mono">{{
                        item.key
                    }}</span>
                    <span class="shrink-0 text-muted-foreground">{{
                        item.status === 'cancelled'
                            ? $t('Cancelled')
                            : formatBytes(item.file.size, locale)
                    }}</span>
                    <button
                        v-if="
                            item.status === 'queued' ||
                            item.status === 'uploading'
                        "
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        :aria-label="$t('Cancel')"
                        @click="$emit('cancel', item)"
                    >
                        <X class="size-3" />
                    </button>
                    <button
                        v-if="item.status === 'failed'"
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        :aria-label="$t('Retry')"
                        @click="$emit('retry', item)"
                    >
                        <RotateCcw class="size-3" />
                    </button>
                </div>
                <div
                    v-if="item.status === 'uploading'"
                    class="h-1 overflow-hidden rounded bg-muted"
                >
                    <div
                        class="h-full bg-primary transition-[width]"
                        :style="{ width: `${percent(item)}%` }"
                    />
                </div>
                <p v-if="item.error" class="text-red-600">{{ item.error }}</p>
            </div>
        </div>
    </div>
</template>
