<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserPermissionController from '@/actions/App/Http/Controllers/User/UserPermissionController';
import Heading from '@/components/Heading.vue';
import PermissionCheckboxes from '@/components/permissions/PermissionCheckboxes.vue';
import PermissionTemplatePicker from '@/components/permissions/PermissionTemplatePicker.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { PermissionOption, PermissionTemplate, User } from '@/types';

const props = defineProps<{
    user: User;
    options: PermissionOption[];
    templates: PermissionTemplate[];
    canUpdate: boolean;
}>();

const selected = ref<number[]>([...props.user.permissions]);
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
                <PermissionTemplatePicker
                    :templates="templates"
                    :options="options"
                    @apply="(permissions) => (selected = permissions)"
                />

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
