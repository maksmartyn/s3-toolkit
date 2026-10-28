<script setup lang="ts">
import { FolderPlus } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { ref } from 'vue';
import ObjectManageController from '@/actions/App/Http/Controllers/Bucket/ObjectManageController';
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
import { Spinner } from '@/components/ui/spinner';
import { HttpValidationError, requestJson } from '@/lib/http';

const props = defineProps<{
    storage: number;
    bucket: string;
    prefix: string;
}>();

const emit = defineEmits<{ created: [] }>();

const open = ref(false);
const name = ref('');
const error = ref<string | undefined>();
const processing = ref(false);

async function submit(): Promise<void> {
    error.value = undefined;
    processing.value = true;

    try {
        const { message } = await requestJson<{ message: string }>(
            'POST',
            ObjectManageController.createFolder.url({
                storage: props.storage,
                bucket: props.bucket,
            }),
            { prefix: props.prefix, name: name.value },
        );
        toast.success(message);
        open.value = false;
        name.value = '';
        emit('created');
    } catch (e) {
        error.value =
            e instanceof HttpValidationError
                ? (e.errors.name ?? e.message)
                : (e as Error).message;
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" data-test="new-folder">
                <FolderPlus />
                {{ $t('New folder') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('New folder') }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'In S3 a folder is a common key prefix. An empty folder is stored as an empty object with a name ending in “/”.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <Input
                    v-model="name"
                    :aria-label="$t('Folder name')"
                    :placeholder="$t('Folder name')"
                    class="font-mono"
                    data-test="new-folder-name"
                    required
                />
                <InputError :message="error" />
                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="processing || name === ''"
                        data-test="new-folder-submit"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('Create') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
