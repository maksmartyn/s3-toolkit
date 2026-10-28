<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import BucketController from '@/actions/App/Http/Controllers/Bucket/BucketController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Bucket, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: Bucket;
    disabled?: boolean;
}>();

const open = ref(false);
const confirmation = ref('');
const error = ref<string | undefined>();
const processing = ref(false);

watch(open, () => {
    confirmation.value = '';
    error.value = undefined;
});

function submit(): void {
    router.delete(
        BucketController.destroy.url({
            storage: props.storage.id,
            bucket: props.bucket.name,
        }),
        {
            data: { confirmation: confirmation.value },
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (e) => (error.value = e.confirmation),
            onSuccess: () => {
                open.value = false;
                router.reload({ only: ['buckets'] });
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                :disabled="disabled"
                class="text-destructive"
                :data-test="`delete-bucket-${bucket.name}`"
                >{{ $t('Delete') }}</Button
            >
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        $t('Delete bucket :name?', { name: bucket.name })
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'All objects, their versions and unfinished uploads will be permanently deleted, then the bucket itself. This cannot be undone.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="bucket-confirmation">{{
                        $t('Type :name to confirm', { name: bucket.name })
                    }}</Label>
                    <Input
                        id="bucket-confirmation"
                        v-model="confirmation"
                        autocomplete="off"
                        class="font-mono"
                    />
                    <InputError :message="error" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >{{ $t('Cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing || confirmation !== bucket.name"
                        data-test="confirm-delete-bucket-button"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('Delete bucket') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
