<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    bucket: string;
    prefix: string;
    move: boolean;
    count: number;
    buckets?: string[];
    /** Выполнить перенос; ошибки валидации — по полям */
    onSubmit: (targetBucket: string, targetPrefix: string) => Promise<void>;
}>();

const open = defineModel<boolean>('open', { required: true });

const targetBucket = ref(props.bucket);
const targetPrefix = ref(props.prefix);
const error = ref<string | null>(null);
const processing = ref(false);

watch(open, (value) => {
    if (!value) {
        return;
    }

    targetBucket.value = props.bucket;
    targetPrefix.value = props.prefix;
    error.value = null;

    // Список бакетов нужен только здесь — загружаем при первом открытии
    if (props.buckets === undefined) {
        router.reload({ only: ['buckets'] });
    }
});

async function submit(): Promise<void> {
    error.value = null;
    processing.value = true;

    try {
        await props.onSubmit(targetBucket.value, targetPrefix.value);
        open.value = false;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        move ? $t('Move') : $t('Copy')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        $t(
                            'Selected: :count. Folders are processed in the background.',
                            {
                                count: String(count),
                            },
                        )
                    }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="target-bucket">{{ $t('Bucket') }}</Label>
                    <select
                        id="target-bucket"
                        v-model="targetBucket"
                        class="rounded-md border border-input bg-transparent px-2 py-1.5 font-mono text-sm"
                        data-test="target-bucket"
                    >
                        <option
                            v-for="name in buckets ?? [bucket]"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="target-prefix">{{
                        $t('Folder (empty — bucket root)')
                    }}</Label>
                    <Input
                        id="target-prefix"
                        v-model="targetPrefix"
                        class="font-mono"
                        :placeholder="$t('for example archive/2026/')"
                        data-test="target-prefix"
                    />
                </div>
                <InputError :message="error ?? undefined" />

                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="transfer-submit"
                    >
                        <Spinner v-if="processing" />
                        {{ move ? $t('Move') : $t('Copy') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
