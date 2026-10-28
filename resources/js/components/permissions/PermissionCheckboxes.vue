<script setup lang="ts">
import { ref, watch } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { PermissionOption } from '@/types';

const props = defineProps<{
    options: PermissionOption[];
    /** Префикс id, чтобы на странице могло быть несколько списков */
    idPrefix: string;
    disabled?: boolean;
}>();

const model = defineModel<number[]>({ required: true });

// Локальная копия: модель родителя обновляется только после перерисовки,
// и несколько изменений подряд иначе затирали бы друг друга
const selected = ref<number[]>([...model.value]);

watch(model, (value) => (selected.value = [...value]));

function set(next: number[]): void {
    selected.value = next.sort((a, b) => a - b);
    model.value = [...selected.value];
}

function toggle(value: number, checked: boolean | 'indeterminate'): void {
    const next = selected.value.filter((item) => item !== value);

    if (checked === true) {
        next.push(value);
    }

    set(next);
}

function allSelected(): boolean {
    return props.options.every((option) =>
        selected.value.includes(option.value),
    );
}

function toggleAll(): void {
    set(allSelected() ? [] : props.options.map((option) => option.value));
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="grid gap-2 sm:grid-cols-2">
            <Label
                v-for="option in options"
                :key="option.value"
                :for="`${idPrefix}-${option.value}`"
                class="flex items-start gap-3 rounded-md border p-3 font-normal"
                :class="{ 'opacity-60': disabled }"
            >
                <Checkbox
                    :id="`${idPrefix}-${option.value}`"
                    :model-value="selected.includes(option.value)"
                    :disabled="disabled"
                    :data-test="`permission-${option.name}`"
                    @update:model-value="
                        (checked) => toggle(option.value, checked)
                    "
                />
                <span>{{ option.label }}</span>
            </Label>
        </div>
        <button
            v-if="!disabled"
            type="button"
            class="self-start text-sm text-muted-foreground underline-offset-4 hover:underline"
            @click="toggleAll"
        >
            {{ allSelected() ? $t('Clear all') : $t('Select all') }}
        </button>
    </div>
</template>
