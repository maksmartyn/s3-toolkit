<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { ButtonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel: string;
        variant?: ButtonVariants['variant'];
        /** Действие; диалог закроется, когда промис завершится. */
        onConfirm: () => Promise<unknown> | void;
    }>(),
    { variant: 'default', description: undefined },
);

const open = ref(false);
const processing = ref(false);

async function confirm(): Promise<void> {
    processing.value = true;

    try {
        await props.onConfirm();
        open.value = false;
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">{{ $t('Cancel') }}</Button>
                </DialogClose>
                <Button
                    :variant="variant"
                    :disabled="processing"
                    data-test="confirm-button"
                    @click="confirm"
                >
                    <Spinner v-if="processing" />
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
