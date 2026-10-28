<script setup lang="ts">
import { CircleX } from '@lucide/vue';

defineProps<{ message: string }>();
</script>

<template>
    <div
        class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-4 text-sm"
    >
        <CircleX class="size-5 shrink-0 text-red-600" />
        <p>{{ message }}</p>
    </div>
</template>
