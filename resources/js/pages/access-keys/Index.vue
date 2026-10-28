<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CircleX } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { toast } from 'vue-sonner';
import { reactive, ref } from 'vue';
import AccessKeyController from '@/actions/App/Http/Controllers/Storage/AccessKeyController';
import CreateAccessKeyDialog from '@/components/access-keys/CreateAccessKeyDialog.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { requestJson } from '@/lib/http';
import { index, show as showStorage } from '@/routes/storages';
import type {
    AccessKey,
    AccessKeyOverview,
    AccessKeySettings,
    Storage,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

const props = defineProps<{
    storage: Storage;
    settings: AccessKeySettings;
    overview?: AccessKeyOverview | null;
    can: { manage: boolean; configure: boolean };
}>();

const locale = usePage().props.locale;

const reloadKeys = () => router.reload({ only: ['overview'] });

function selectUser(user: string): void {
    router.get(
        AccessKeyController.index.url(props.storage.id, {
            query: { user: user || null },
        }),
        {},
        { preserveState: true, replace: true, only: ['overview'] },
    );
}

const statusVariant = (key: AccessKey) =>
    key.status === 'active' ? 'default' : 'secondary';

const statusLabel = (key: AccessKey) =>
    ({
        active: trans('Active'),
        inactive: trans('Disabled'),
        expired: trans('Expired'),
    })[key.status];

async function change(
    method: 'PUT' | 'DELETE',
    url: string,
    body: Record<string, unknown>,
): Promise<void> {
    try {
        const { message } = await requestJson<{ message: string }>(
            method,
            url,
            body,
        );
        toast.success(message);
        reloadKeys();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

const setStatus = (key: AccessKey, active: boolean) =>
    change('PUT', AccessKeyController.status.url(props.storage.id), {
        id: key.id,
        user: key.user,
        active,
    });

const remove = (key: AccessKey) =>
    change('DELETE', AccessKeyController.destroy.url(props.storage.id), {
        id: key.id,
        user: key.user,
    });

// Учётные данные управления
const credentials = reactive<Record<string, string>>(
    Object.fromEntries(
        props.settings.fields.map((field) => [field.name, field.value ?? '']),
    ),
);
const credentialErrors = ref<Record<string, string>>({});
const saving = ref(false);

function saveCredentials(): void {
    router.put(
        AccessKeyController.credentials.url(props.storage.id),
        { credentials: { ...credentials } },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onError: (e) => (credentialErrors.value = e),
            onSuccess: () => {
                // Секреты после сохранения не показываем
                for (const field of props.settings.fields) {
                    if (field.secret) {
                        credentials[field.name] = '';
                    }
                }
            },
        },
    );
}
</script>

<template>
    <Head :title="$t('Access keys')" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <Link
                :href="showStorage(storage.id)"
                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ storage.name }}
            </Link>
            <Heading
                :title="$t('Access keys')"
                :description="
                    $t(
                        'S3 access keys created through the provider API. The secret key is shown only once, right after creation.',
                    )
                "
            />
        </div>

        <p
            v-if="!settings.supported"
            class="rounded-md bg-muted p-3 text-sm text-muted-foreground"
        >
            {{
                $t(
                    'Key management is not available for this provider. Create keys in the provider console.',
                )
            }}
        </p>

        <template v-else>
            <p
                v-if="!settings.configured"
                class="rounded-md bg-muted p-3 text-sm text-muted-foreground"
            >
                {{
                    can.configure
                        ? $t(
                              'Fill in the key management credentials below to manage keys.',
                          )
                        : $t(
                              'Key management is not configured yet. Ask a storage administrator to set it up.',
                          )
                }}
            </p>

            <section
                v-if="can.manage && settings.configured && settings.features"
                class="flex flex-col gap-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div
                        v-if="settings.features.users && overview"
                        class="flex items-center gap-2"
                    >
                        <Label for="key-owner">{{ $t('User') }}</Label>
                        <select
                            id="key-owner"
                            class="rounded-md border border-input bg-transparent px-2 py-1.5 font-mono text-sm"
                            :value="overview.user ?? ''"
                            data-test="key-owner"
                            @change="
                                selectUser(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">
                                {{ $t('Key management account') }}
                            </option>
                            <option
                                v-for="name in overview.users"
                                :key="name"
                                :value="name"
                            >
                                {{ name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex-1" />
                    <CreateAccessKeyDialog
                        :storage="storage.id"
                        :features="settings.features"
                        :users="overview?.users ?? []"
                        :user="overview?.user ?? null"
                        @created="reloadKeys"
                    />
                </div>

                <Deferred data="overview">
                    <template #fallback>
                        <div class="flex flex-col gap-2">
                            <Skeleton class="h-10 w-full" />
                            <Skeleton class="h-10 w-full" />
                        </div>
                    </template>

                    <div
                        v-if="overview?.error"
                        class="flex gap-3 rounded-lg border border-red-600/40 bg-red-600/10 p-4 text-sm"
                    >
                        <CircleX class="size-5 shrink-0 text-red-600" />
                        <p>{{ overview.error }}</p>
                    </div>

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2 font-medium">
                                        {{ $t('Access key ID') }}
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        {{ $t('Name') }}
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        {{ $t('Status') }}
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        {{ $t('Created') }}
                                    </th>
                                    <th
                                        v-if="settings.features.expiration"
                                        class="px-4 py-2 font-medium"
                                    >
                                        {{ $t('Expires') }}
                                    </th>
                                    <th class="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="key in overview?.keys ?? []"
                                    :key="key.id"
                                    class="border-t align-top"
                                    data-test="access-key-row"
                                >
                                    <td class="px-4 py-2 font-mono">
                                        {{ key.accessKeyId }}
                                        <div
                                            v-if="key.user"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ key.user }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ key.name ?? '' }}
                                        <div
                                            v-if="key.description"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ key.description }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Badge :variant="statusVariant(key)">{{
                                            statusLabel(key)
                                        }}</Badge>
                                    </td>
                                    <td
                                        class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                                    >
                                        {{
                                            formatDateTime(
                                                key.createdAt,
                                                locale,
                                            ) ?? '—'
                                        }}
                                    </td>
                                    <td
                                        v-if="settings.features.expiration"
                                        class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                                    >
                                        {{
                                            formatDateTime(
                                                key.expiresAt,
                                                locale,
                                            ) ?? '—'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right whitespace-nowrap"
                                    >
                                        <Button
                                            v-if="
                                                settings.features.status &&
                                                key.status !== 'expired'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            :data-test="`toggle-${key.accessKeyId}`"
                                            @click="
                                                setStatus(
                                                    key,
                                                    key.status !== 'active',
                                                )
                                            "
                                            >{{
                                                key.status === 'active'
                                                    ? $t('Disable')
                                                    : $t('Enable')
                                            }}</Button
                                        >
                                        <ConfirmDialog
                                            :title="trans('Delete the key?')"
                                            :description="
                                                trans(
                                                    'Applications using :key will lose access immediately.',
                                                    { key: key.accessKeyId },
                                                )
                                            "
                                            :confirm-label="trans('Delete')"
                                            variant="destructive"
                                            :on-confirm="() => remove(key)"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive"
                                                :data-test="`delete-${key.accessKeyId}`"
                                                >{{ $t('Delete') }}</Button
                                            >
                                        </ConfirmDialog>
                                    </td>
                                </tr>
                                <tr v-if="(overview?.keys ?? []).length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        {{ $t('No keys.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Deferred>
            </section>

            <form
                v-if="can.configure"
                class="flex max-w-2xl flex-col gap-4 border-t pt-6"
                @submit.prevent="saveCredentials"
            >
                <Heading
                    variant="small"
                    :title="$t('Key management credentials')"
                    :description="
                        $t(
                            'Credentials with the right to manage keys at the provider. Stored encrypted; secret fields are never shown again — leave them empty to keep the current value.',
                        )
                    "
                />

                <div
                    v-for="field in settings.fields"
                    :key="field.name"
                    class="grid gap-2"
                >
                    <Label :for="`credential-${field.name}`">
                        {{ field.label }}
                        <span v-if="field.required" class="text-destructive"
                            >*</span
                        >
                    </Label>
                    <textarea
                        v-if="field.multiline"
                        :id="`credential-${field.name}`"
                        v-model="credentials[field.name]"
                        rows="5"
                        class="rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs"
                        :placeholder="
                            field.isSet ? $t('Saved. Leave empty to keep.') : ''
                        "
                        autocomplete="off"
                        spellcheck="false"
                    />
                    <PasswordInput
                        v-else-if="field.secret"
                        :id="`credential-${field.name}`"
                        v-model="credentials[field.name]"
                        :placeholder="
                            field.isSet ? $t('Saved. Leave empty to keep.') : ''
                        "
                        autocomplete="new-password"
                    />
                    <Input
                        v-else
                        :id="`credential-${field.name}`"
                        v-model="credentials[field.name]"
                        :placeholder="field.placeholder ?? ''"
                        autocomplete="off"
                    />
                    <p v-if="field.hint" class="text-xs text-muted-foreground">
                        {{ field.hint }}
                    </p>
                    <InputError
                        :message="credentialErrors[`credentials.${field.name}`]"
                    />
                </div>

                <Button
                    type="submit"
                    class="self-start"
                    :disabled="saving"
                    data-test="save-credentials"
                >
                    <Spinner v-if="saving" />
                    {{ $t('Save') }}
                </Button>
            </form>
        </template>
    </div>
</template>
