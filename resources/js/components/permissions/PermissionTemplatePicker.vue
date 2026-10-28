<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PermissionOption, PermissionTemplate } from '@/types';

const props = defineProps<{
    templates: PermissionTemplate[];
    options: PermissionOption[];
}>();

const emit = defineEmits<{
    /** Права шаблона, известные текущему списку; заменяют текущий выбор */
    apply: [permissions: number[]];
}>();

const templateId = ref('');

function apply(): void {
    const template = props.templates.find(
        (item) => String(item.id) === templateId.value,
    );

    if (template) {
        const known = props.options.map((option) => option.value);
        emit(
            'apply',
            template.permissions.filter((value) => known.includes(value)),
        );
    }
}
</script>

<template>
    <div v-if="templates.length > 0" class="flex flex-wrap items-center gap-2">
        <Select v-model="templateId">
            <SelectTrigger class="w-64">
                <SelectValue :placeholder="$t('Choose a template')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="template in templates"
                    :key="template.id"
                    :value="String(template.id)"
                    >{{ template.name }}</SelectItem
                >
            </SelectContent>
        </Select>
        <Button
            type="button"
            variant="outline"
            :disabled="!templateId"
            @click="apply"
        >
            {{ $t('Apply') }}
        </Button>
    </div>
</template>
