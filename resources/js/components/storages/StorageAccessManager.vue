<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import StorageAccessController from '@/actions/App/Http/Controllers/Storage/StorageAccessController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import PermissionCheckboxes from '@/components/permissions/PermissionCheckboxes.vue';
import PermissionTemplatePicker from '@/components/permissions/PermissionTemplatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type {
    PermissionOption,
    PermissionTemplate,
    Storage,
    StorageAccess,
    User,
} from '@/types';

const props = defineProps<{
    storage: Storage;
    accesses: StorageAccess[];
    candidates: User[];
    options: PermissionOption[];
    templates: PermissionTemplate[];
}>();

const open = ref(false);
const editing = ref<StorageAccess | null>(null);
const userId = ref('');
const selected = ref<number[]>([]);
const processing = ref(false);

// Новый доступ — только тем, у кого его ещё нет
const available = computed(() =>
    props.candidates.filter(
        (user) => !props.accesses.some((access) => access.userId === user.id),
    ),
);

function labels(permissions: number[]): string[] {
    return props.options
        .filter((option) => permissions.includes(option.value))
        .map((option) => option.label);
}

function startGrant(): void {
    editing.value = null;
    userId.value = '';
    selected.value = [];
    open.value = true;
}

function startEdit(access: StorageAccess): void {
    editing.value = access;
    userId.value = String(access.userId);
    selected.value = [...access.permissions];
    open.value = true;
}

function submit(permissions: number[], targetId: string): Promise<void> {
    return new Promise((resolve) => {
        router.put(
            StorageAccessController.update.url(props.storage.id),
            { user_id: Number(targetId), permissions },
            {
                preserveScroll: true,
                onStart: () => (processing.value = true),
                onSuccess: () => (open.value = false),
                onFinish: () => {
                    processing.value = false;
                    resolve();
                },
            },
        );
    });
}

const revoke = (access: StorageAccess) => () =>
    submit([], String(access.userId));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('Access')"
                :description="
                    $t(
                        'Who can work with this storage. Superadmins have all permissions.',
                    )
                "
            />
            <Button
                variant="outline"
                :disabled="available.length === 0"
                data-test="grant-access-button"
                @click="startGrant"
            >
                <Plus />
                {{ $t('Grant access') }}
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('User') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Permissions') }}
                        </th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="access in accesses"
                        :key="access.userId"
                        class="border-t align-top"
                    >
                        <td class="px-4 py-2">
                            <div class="font-medium">{{ access.userName }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ access.userEmail }}
                            </div>
                            <Badge
                                v-if="access.userStatus === 'blocked'"
                                variant="destructive"
                                class="mt-1"
                                >{{ $t('Blocked') }}</Badge
                            >
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ labels(access.permissions).join(', ') }}
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="startEdit(access)"
                                >{{ $t('Edit') }}</Button
                            >
                            <ConfirmDialog
                                :title="trans('Revoke access?')"
                                :description="access.userName"
                                :confirm-label="trans('Revoke')"
                                variant="destructive"
                                :on-confirm="revoke(access)"
                            >
                                <Button variant="ghost" size="sm">{{
                                    $t('Revoke')
                                }}</Button>
                            </ConfirmDialog>
                        </td>
                    </tr>
                    <tr v-if="accesses.length === 0">
                        <td
                            colspan="3"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('Nobody has access yet.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? editing.userName : $t('Grant access')
                    }}</DialogTitle>
                    <DialogDescription>{{ storage.name }}</DialogDescription>
                </DialogHeader>

                <div class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto">
                    <div v-if="!editing" class="grid gap-2">
                        <Label>{{ $t('User') }}</Label>
                        <Select v-model="userId">
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    :placeholder="$t('Choose a user')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="user in available"
                                    :key="user.id"
                                    :value="String(user.id)"
                                    >{{ user.name }} ({{
                                        user.email
                                    }})</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <PermissionTemplatePicker
                        :templates="templates"
                        :options="options"
                        @apply="(permissions) => (selected = permissions)"
                    />

                    <PermissionCheckboxes
                        v-model="selected"
                        :options="options"
                        id-prefix="storage-permission"
                    />
                </div>

                <DialogFooter>
                    <Button variant="secondary" @click="open = false">{{
                        $t('Cancel')
                    }}</Button>
                    <Button
                        :disabled="processing || !userId"
                        data-test="save-access-button"
                        @click="submit(selected, userId)"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('Save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
