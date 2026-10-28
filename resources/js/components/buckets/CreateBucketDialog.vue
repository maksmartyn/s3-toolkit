<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import BucketController from '@/actions/App/Http/Controllers/Bucket/BucketController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
}>();

const open = ref(false);
const name = ref('');
const objectLock = ref(false);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit(): void {
    errors.value = {};

    router.post(
        BucketController.store.url(props.storage.id),
        { name: name.value, object_lock: objectLock.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (e) => (errors.value = e),
            onSuccess: () => {
                open.value = false;
                name.value = '';
                objectLock.value = false;
                router.reload({ only: ['buckets'] });
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" data-test="create-bucket-button">
                <Plus />
                {{ $t('Create bucket') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('New bucket') }}</DialogTitle>
                    <DialogDescription>{{ storage.name }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="bucket-name">{{ $t('Name') }}</Label>
                    <Input
                        id="bucket-name"
                        v-model="name"
                        required
                        autocomplete="off"
                        class="font-mono"
                    />
                    <InputError :message="errors.name" />
                </div>

                <Label
                    v-if="storage.capabilities.includes('object_lock')"
                    class="flex items-start gap-3 font-normal"
                >
                    <Checkbox v-model="objectLock" class="mt-0.5" />
                    <span>
                        {{ $t('Enable Object Lock') }}
                        <span class="block text-sm text-muted-foreground">{{
                            $t(
                                'Can only be enabled when the bucket is created. Versioning will be turned on automatically.',
                            )
                        }}</span>
                    </span>
                </Label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                        >{{ $t('Cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="submit-bucket-button"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('Create') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
