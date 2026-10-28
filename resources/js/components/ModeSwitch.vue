<script setup lang="ts">
/** Переключатель «Форма / JSON» */
const mode = defineModel<'form' | 'json'>({ required: true });

defineProps<{
    formDisabled?: boolean;
}>();
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-muted p-1 text-sm"
        role="tablist"
    >
        <button
            v-for="item in ['form', 'json'] as const"
            :key="item"
            type="button"
            role="tab"
            :aria-selected="mode === item"
            :disabled="item === 'form' && formDisabled"
            class="rounded-md px-3 py-1 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
            :class="
                mode === item
                    ? 'bg-background shadow-xs'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :data-test="`mode-${item}`"
            @click="mode = item"
        >
            {{ item === 'form' ? $t('Form') : 'JSON' }}
        </button>
    </div>
</template>
