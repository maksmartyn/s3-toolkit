<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import PermissionTemplateController from '@/actions/App/Http/Controllers/Permission/PermissionTemplateController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PermissionCheckboxes from '@/components/permissions/PermissionCheckboxes.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/permission-templates';
import type { PermissionScopeData, PermissionTemplate } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Permission templates', href: index() }],
    },
});

const props = defineProps<{
    template: PermissionTemplate | null;
    scope: PermissionScopeData;
}>();

const name = ref(props.template?.name ?? '');
const permissions = ref<number[]>([...(props.template?.permissions ?? [])]);
const errors = ref<Record<string, string>>({});
const processing = ref(false);

const title = computed(() =>
    props.template ? props.template.name : trans('New template'),
);

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
    onError: (e: Record<string, string>) => (errors.value = e),
};

function save(): void {
    errors.value = {};

    if (props.template) {
        router.put(
            PermissionTemplateController.update.url(props.template.id),
            { name: name.value, permissions: permissions.value },
            options,
        );
    } else {
        router.post(
            PermissionTemplateController.store.url(),
            {
                scope: props.scope.value,
                name: name.value,
                permissions: permissions.value,
            },
            options,
        );
    }
}

function destroy(): Promise<void> {
    return new Promise((resolve) => {
        if (!props.template) {
            return resolve();
        }

        router.delete(
            PermissionTemplateController.destroy.url(props.template.id),
            { onFinish: () => resolve() },
        );
    });
}
</script>

<template>
    <Head :title="title" />

    <form class="flex max-w-3xl flex-col gap-6 p-4" @submit.prevent="save">
        <Heading :title="title" :description="scope.label" />

        <div class="grid max-w-xl gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input id="name" v-model="name" required v-focus />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label>{{ $t('Permissions') }}</Label>
            <PermissionCheckboxes
                v-model="permissions"
                :options="scope.options"
                id-prefix="template-permission"
            />
            <InputError :message="errors.permissions" />
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="submit"
                :disabled="processing"
                data-test="save-template-button"
            >
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>

            <ConfirmDialog
                v-if="template"
                :title="trans('Delete template?')"
                :description="
                    trans(
                        'Permissions already granted with this template will not change.',
                    )
                "
                :confirm-label="trans('Delete')"
                variant="destructive"
                :on-confirm="destroy"
            >
                <Button type="button" variant="destructive">{{
                    $t('Delete')
                }}</Button>
            </ConfirmDialog>
        </div>
    </form>
</template>
