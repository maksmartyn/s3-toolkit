<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        id?: string;
        rows?: number;
        disabled?: boolean;
    }>(),
    { id: undefined, rows: 16, disabled: false },
);

const model = defineModel<string>({ required: true });

const parseError = computed(() => {
    if (model.value.trim() === '') {
        return null;
    }

    try {
        JSON.parse(model.value);

        return null;
    } catch (e) {
        return e instanceof Error ? e.message : String(e);
    }
});

function format(): void {
    if (parseError.value === null && model.value.trim() !== '') {
        model.value = JSON.stringify(JSON.parse(model.value), null, 2);
    }
}

defineExpose({ parseError });
</script>

<template>
    <div class="flex flex-col gap-2">
        <textarea
            :id="props.id"
            v-model="model"
            :rows="props.rows"
            :disabled="props.disabled"
            spellcheck="false"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs leading-5 shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60"
            :class="{ 'border-destructive': parseError }"
            data-test="json-editor"
        />
        <div class="flex items-center justify-between gap-2 text-xs">
            <span v-if="parseError" class="text-destructive">{{
                $t('Invalid JSON: :error', { error: parseError })
            }}</span>
            <span v-else />
            <Button
                v-if="!props.disabled"
                type="button"
                variant="ghost"
                size="sm"
                :disabled="parseError !== null"
                @click="format"
                >{{ $t('Format') }}</Button
            >
        </div>
    </div>
</template>
