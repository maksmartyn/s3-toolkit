<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TemporaryPassword } from '@/types';

const page = usePage();

// Пароль приходит во flash один раз; копию держим локально, чтобы диалог не пропал при следующем запросе
const credentials = ref<TemporaryPassword | null>(null);
const open = ref(false);

watch(
    () => page.flash.temporaryPassword,
    (value) => {
        if (value) {
            credentials.value = value;
            open.value = true;
        }
    },
    { immediate: true },
);

watch(open, (value) => {
    if (!value) {
        credentials.value = null;
    }
});

const { copy, copied, isSupported } = useClipboard({ legacy: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="credentials">
            <DialogHeader>
                <DialogTitle>{{ $t('Temporary password') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Give this password to the user. It is shown only once; the user will have to change it on first login.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2 text-sm">
                <div class="text-muted-foreground">{{ credentials.email }}</div>
                <div class="flex items-center gap-2">
                    <code
                        class="flex-1 rounded-md bg-muted px-3 py-2 font-mono text-base select-all"
                        data-test="temporary-password"
                        >{{ credentials.password }}</code
                    >
                    <Button
                        v-if="isSupported"
                        variant="outline"
                        size="icon"
                        :aria-label="$t('Copy')"
                        @click="copy(credentials.password)"
                    >
                        <Check v-if="copied" />
                        <Copy v-else />
                    </Button>
                </div>
            </div>

            <DialogFooter>
                <Button @click="open = false">{{ $t('Done') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
