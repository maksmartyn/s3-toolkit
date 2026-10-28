<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ref, watch } from 'vue';
import ObjectController from '@/actions/App/Http/Controllers/Bucket/ObjectController';
import ObjectManageController from '@/actions/App/Http/Controllers/Bucket/ObjectManageController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { getJson, requestJson } from '@/lib/http';
import type { MultipartUpload } from '@/types';

const props = defineProps<{
    storage: number;
    bucket: string;
    canAbort: boolean;
}>();

const locale = usePage().props.locale;
const open = ref(false);
const uploads = ref<MultipartUpload[] | null>(null);
const error = ref<string | null>(null);
const processing = ref(false);
const args = () => ({ storage: props.storage, bucket: props.bucket });

async function load(): Promise<void> {
    uploads.value = null;
    error.value = null;

    try {
        uploads.value = await getJson<MultipartUpload[]>(
            ObjectController.uploads.url(args()),
        );
    } catch (e) {
        error.value = (e as Error).message;
    }
}

watch(open, (value) => value && void load());

async function abort(list: MultipartUpload[]): Promise<void> {
    processing.value = true;

    try {
        const { message } = await requestJson<{ message: string }>(
            'DELETE',
            ObjectManageController.abortUploads.url(args()),
            {
                uploads: list.map((upload) => ({
                    key: upload.key,
                    upload_id: upload.uploadId,
                })),
            },
        );
        toast.success(message);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" data-test="open-uploads">
                {{ $t('Unfinished uploads') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Unfinished uploads') }}</DialogTitle>
                <DialogDescription>{{
                    $t(
                        'Multipart uploads that were started but never completed. Their parts take up space until aborted.',
                    )
                }}</DialogDescription>
            </DialogHeader>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            <Spinner v-else-if="uploads === null" />
            <p
                v-else-if="uploads.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No unfinished uploads.') }}
            </p>
            <div v-else class="flex max-h-96 flex-col gap-2 overflow-y-auto">
                <div
                    v-for="upload in uploads"
                    :key="upload.uploadId"
                    class="flex items-center justify-between gap-2 border-b pb-2 text-sm"
                >
                    <div class="min-w-0">
                        <p class="truncate font-mono">{{ upload.key }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDateTime(upload.initiated, locale) }}
                        </p>
                    </div>
                    <Button
                        v-if="canAbort"
                        variant="ghost"
                        size="sm"
                        :disabled="processing"
                        @click="abort([upload])"
                        >{{ $t('Abort') }}</Button
                    >
                </div>
                <Button
                    v-if="canAbort && uploads.length > 1"
                    variant="destructive"
                    class="self-start"
                    :disabled="processing"
                    @click="abort(uploads)"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Abort all') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
