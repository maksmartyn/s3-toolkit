<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserPermissionController from '@/actions/App/Http/Controllers/User/UserPermissionController';
import Heading from '@/components/Heading.vue';
import PermissionCheckboxes from '@/components/permissions/PermissionCheckboxes.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { PermissionOption, PermissionTemplate, User } from '@/types';

const props = defineProps<{
    user: User;
    options: PermissionOption[];
    templates: PermissionTemplate[];
    canUpdate: boolean;
}>();

const selected = ref<number[]>([...props.user.permissions]);
const templateId = ref<string>('');
const processing = ref(false);

watch(
    () => props.user.permissions,
    (permissions) => (selected.value = [...permissions]),
);

const dirty = computed(
    () =>
        [...selected.value].sort().join() !==
        [...props.user.permissions].sort().join(),
);

const granted = computed(() =>
    props.options.filter((option) =>
        props.user.permissions.includes(option.value),
    ),
);

// Шаблон заменяет текущий выбор; сохранить нужно явно
function applyTemplate(): void {
    const template = props.templates.find(
        (item) => String(item.id) === templateId.value,
    );

    if (template) {
        const known = props.options.map((option) => option.value);
        selected.value = template.permissions.filter((value) =>
            known.includes(value),
        );
    }
}

function save(): void {
    router.put(
        UserPermissionController.update.url(props.user.id),
        { permissions: selected.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('System permissions')"
            :description="
                user.isSuperadmin
                    ? $t('A superadmin already has all permissions.')
                    : undefined
            "
        />

        <template v-if="!user.isSuperadmin">
            <template v-if="canUpdate">
                <div
                    v-if="templates.length > 0"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Select v-model="templateId">
                        <SelectTrigger class="w-64">
                            <SelectValue
                                :placeholder="$t('Choose a template')"
                            />
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
                        variant="outline"
                        :disabled="!templateId"
                        @click="applyTemplate"
                    >
                        {{ $t('Apply') }}
                    </Button>
                </div>

                <PermissionCheckboxes
                    v-model="selected"
                    :options="options"
                    id-prefix="system-permission"
                />

                <Button
                    class="self-start"
                    :disabled="!dirty || processing"
                    data-test="save-permissions-button"
                    @click="save"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save permissions') }}
                </Button>
            </template>

            <template v-else>
                <ul v-if="granted.length > 0" class="list-disc pl-5 text-sm">
                    <li v-for="option in granted" :key="option.value">
                        {{ option.label }}
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ $t('No permissions granted.') }}
                </p>
            </template>
        </template>
    </div>
</template>
