<script setup lang="ts">
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        rows?: number;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    { id: undefined, rows: 3, placeholder: undefined, disabled: false },
);

/** Список строк; в поле — по одной на строку, пустые строки отбрасываются */
const model = defineModel<string[]>({ required: true });

const text = ref(model.value.join('\n'));

watch(model, (value) => {
    if (
        value.join('\n') !== text.value.split('\n').filter(Boolean).join('\n')
    ) {
        text.value = value.join('\n');
    }
});

function update(value: string): void {
    text.value = value;
    model.value = value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}
</script>

<template>
    <textarea
        :id="props.id"
        :value="text"
        :rows="props.rows"
        :placeholder="props.placeholder"
        :disabled="props.disabled"
        class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60"
        @input="update(($event.target as HTMLTextAreaElement).value)"
    />
</template>
