<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import UserController from '@/actions/App/Http/Controllers/User/UserController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TemporaryPasswordDialog from '@/components/users/TemporaryPasswordDialog.vue';
import UserSystemPermissions from '@/components/users/UserSystemPermissions.vue';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/users';
import type { PermissionOption, PermissionTemplate, User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const props = defineProps<{
    user: User;
    permissionOptions: PermissionOption[];
    permissionTemplates: PermissionTemplate[];
    can: {
        updatePermissions: boolean;
        update: boolean;
        block: boolean;
        unblock: boolean;
        delete: boolean;
        restore: boolean;
        resetPassword: boolean;
    };
}>();

const locale = usePage().props.locale;

function visit(route: { url: string; method: 'post' | 'delete' }) {
    return new Promise<void>((resolve) => {
        router.visit(route.url, {
            method: route.method,
            preserveScroll: true,
            onFinish: () => resolve(),
        });
    });
}

const resetPassword = () => visit(UserController.resetPassword(props.user.id));
const block = () => visit(UserController.block(props.user.id));
const unblock = () => visit(UserController.unblock(props.user.id));
const restore = () => visit(UserController.restore(props.user.id));
const destroy = () => visit(UserController.destroy(props.user.id));
</script>

<template>
    <Head :title="user.name" />

    <TemporaryPasswordDialog />

    <div class="flex max-w-xl flex-col gap-8 p-4">
        <div class="flex flex-col gap-2">
            <Heading :title="user.name" :description="user.email" />
            <UserStatusBadge :user="user" />
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-muted-foreground">{{ $t('Created') }}</dt>
                <dd>{{ formatDateTime(user.createdAt, locale) }}</dd>
                <dt class="text-muted-foreground">{{ $t('Last login') }}</dt>
                <dd>
                    {{
                        formatDateTime(user.lastLoginAt, locale) ?? $t('Never')
                    }}
                </dd>
                <template v-if="user.passwordChangeRequired">
                    <dt class="text-muted-foreground">{{ $t('Password') }}</dt>
                    <dd>{{ $t('Temporary, must be changed on login') }}</dd>
                </template>
            </dl>
        </div>

        <Form
            v-if="can.update"
            v-bind="UserController.update.form(user.id)"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="user.name"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="user.email"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.email" />
            </div>

            <Button :disabled="processing" data-test="update-user-button">
                {{ $t('Save') }}
            </Button>
        </Form>

        <UserSystemPermissions
            v-if="user.status !== 'deleted'"
            :user="user"
            :options="permissionOptions"
            :templates="permissionTemplates"
            :can-update="can.updatePermissions"
        />

        <div
            v-if="
                can.resetPassword ||
                can.block ||
                can.unblock ||
                can.delete ||
                can.restore
            "
            class="flex flex-col gap-4"
        >
            <Heading variant="small" :title="$t('Actions')" />

            <div class="flex flex-wrap gap-2">
                <ConfirmDialog
                    v-if="can.resetPassword"
                    :title="trans('Reset password?')"
                    :description="
                        trans(
                            'The user will get a new temporary password and will be signed out on all devices.',
                        )
                    "
                    :confirm-label="trans('Reset password')"
                    :on-confirm="resetPassword"
                >
                    <Button variant="outline">{{
                        $t('Reset password')
                    }}</Button>
                </ConfirmDialog>

                <ConfirmDialog
                    v-if="can.block"
                    :title="trans('Block user?')"
                    :description="
                        trans(
                            'The user will be signed out and will not be able to sign in until unblocked.',
                        )
                    "
                    :confirm-label="trans('Block')"
                    variant="destructive"
                    :on-confirm="block"
                >
                    <Button variant="outline">{{ $t('Block') }}</Button>
                </ConfirmDialog>

                <Button v-if="can.unblock" variant="outline" @click="unblock">
                    {{ $t('Unblock') }}
                </Button>

                <Button v-if="can.restore" variant="outline" @click="restore">
                    {{ $t('Restore') }}
                </Button>

                <ConfirmDialog
                    v-if="can.delete"
                    :title="trans('Delete user?')"
                    :description="
                        trans(
                            'The user will lose access. A deleted user can be restored later.',
                        )
                    "
                    :confirm-label="trans('Delete')"
                    variant="destructive"
                    :on-confirm="destroy"
                >
                    <Button variant="destructive">{{ $t('Delete') }}</Button>
                </ConfirmDialog>
            </div>
        </div>
    </div>
</template>
