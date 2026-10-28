<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck, CircleX } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, reactive, ref, watch } from 'vue';
import StorageController from '@/actions/App/Http/Controllers/Storage/StorageController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { HttpValidationError, postJson } from '@/lib/http';
import { index } from '@/routes/storages';
import type {
    ConnectionTestResult,
    ProviderPreset,
    SelectOption,
    StorageCapability,
    StorageDetails,
    StorageProvider,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

const props = defineProps<{
    storage: StorageDetails | null;
    providers: ProviderPreset[];
    capabilityOptions: SelectOption<StorageCapability>[];
}>();

const isEdit = computed(() => props.storage !== null);
const title = computed(() =>
    props.storage ? props.storage.name : trans('New storage'),
);

const form = reactive({
    name: props.storage?.name ?? '',
    provider: (props.storage?.provider ?? 'minio') as StorageProvider,
    endpoint: props.storage?.endpoint ?? '',
    region: props.storage?.region ?? 'us-east-1',
    use_path_style: props.storage?.usePathStyle ?? true,
    verify_ssl: props.storage?.verifySsl ?? true,
    access_key_id: '',
    secret_access_key: '',
    buckets: (props.storage?.buckets ?? []).join('\n'),
    capabilities: [
        ...(props.storage?.capabilities ?? []),
    ] as StorageCapability[],
});

// Cloudflare R2: endpoint собирается из Account ID
const accountId = ref('');

const preset = computed(() =>
    props.providers.find((item) => item.value === form.provider),
);

function applyPreset(): void {
    const current = preset.value;

    if (!current) {
        return;
    }

    form.endpoint = current.defaultEndpoint?.includes('{accountId}')
        ? ''
        : (current.defaultEndpoint ?? '');
    form.region = current.defaultRegion;
    form.use_path_style = current.defaultPathStyle;
    form.capabilities = [...current.capabilities];
    accountId.value = '';
}

if (!props.storage) {
    applyPreset();
}

watch(
    () => form.provider,
    () => applyPreset(),
);

watch(accountId, (value) => {
    const template = preset.value?.defaultEndpoint;

    if (template?.includes('{accountId}')) {
        form.endpoint = value.trim()
            ? template.replace('{accountId}', value.trim())
            : '';
    }
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const testing = ref(false);
const testResult = ref<ConnectionTestResult | null>(null);

function toggleCapability(
    capability: StorageCapability,
    checked: boolean | 'indeterminate',
): void {
    const next = form.capabilities.filter((item) => item !== capability);

    if (checked === true) {
        next.push(capability);
    }

    form.capabilities = next;
}

function payload() {
    return {
        ...form,
        endpoint: form.endpoint.trim() || null,
        buckets: form.buckets,
    };
}

async function testConnection(): Promise<void> {
    testing.value = true;
    testResult.value = null;
    errors.value = {};

    try {
        testResult.value = await postJson<ConnectionTestResult>(
            StorageController.testConnection.url(),
            { ...payload(), storage_id: props.storage?.id ?? null },
        );
    } catch (e) {
        if (e instanceof HttpValidationError) {
            errors.value = e.errors;
        } else {
            testResult.value = {
                ok: false,
                message: trans('Something went wrong.'),
                buckets: [],
                bucketCount: null,
            };
        }
    } finally {
        testing.value = false;
    }
}

function save(): void {
    errors.value = {};

    const options = {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (e: Record<string, string>) => (errors.value = e),
    };

    if (props.storage) {
        router.put(
            StorageController.update.url(props.storage.id),
            payload(),
            options,
        );
    } else {
        router.post(StorageController.store.url(), payload(), options);
    }
}

const isR2 = computed(
    () => preset.value?.defaultEndpoint?.includes('{accountId}') ?? false,
);
</script>

<template>
    <Head :title="title" />

    <form class="flex max-w-2xl flex-col gap-6 p-4" @submit.prevent="save">
        <Heading
            :title="title"
            :description="
                $t(
                    'Connection over the S3 API. Keys are stored encrypted and never shown again.',
                )
            "
        />

        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input id="name" v-model="form.name" required v-focus />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="provider">{{ $t('Provider') }}</Label>
            <Select v-model="form.provider">
                <SelectTrigger id="provider" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="item in providers"
                        :key="item.value"
                        :value="item.value"
                        >{{ item.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="errors.provider" />
        </div>

        <div v-if="isR2" class="grid gap-2">
            <Label for="account_id">{{ $t('Cloudflare Account ID') }}</Label>
            <Input id="account_id" v-model="accountId" autocomplete="off" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
            <div class="grid gap-2">
                <Label for="endpoint">{{ $t('Endpoint') }}</Label>
                <Input
                    id="endpoint"
                    v-model="form.endpoint"
                    :placeholder="
                        preset?.endpointRequired
                            ? 'https://s3.example.com'
                            : $t('Default for the region')
                    "
                    autocomplete="off"
                />
                <InputError :message="errors.endpoint" />
            </div>
            <div class="grid gap-2">
                <Label for="region">{{ $t('Region') }}</Label>
                <Input
                    id="region"
                    v-model="form.region"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.region" />
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.use_path_style" />
                <span>{{
                    $t('Path-style addressing (endpoint/bucket/key)')
                }}</span>
            </Label>
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.verify_ssl" />
                <span>{{ $t('Verify SSL certificate') }}</span>
            </Label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="access_key_id">{{ $t('Access key ID') }}</Label>
                <Input
                    id="access_key_id"
                    v-model="form.access_key_id"
                    :required="!isEdit"
                    :placeholder="storage?.accessKeyIdMasked"
                    autocomplete="off"
                />
                <InputError :message="errors.access_key_id" />
            </div>
            <div class="grid gap-2">
                <Label for="secret_access_key">{{ $t('Secret key') }}</Label>
                <PasswordInput
                    id="secret_access_key"
                    v-model="form.secret_access_key"
                    :required="!isEdit"
                    autocomplete="new-password"
                />
                <InputError :message="errors.secret_access_key" />
            </div>
            <p
                v-if="isEdit"
                class="text-sm text-muted-foreground sm:col-span-2"
            >
                {{
                    $t(
                        'Leave both keys empty to keep the current ones. To replace keys, enter both.',
                    )
                }}
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="buckets">{{ $t('Buckets') }}</Label>
            <textarea
                id="buckets"
                v-model="form.buckets"
                rows="3"
                class="rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                :placeholder="$t('One per line')"
            />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Only if the key is limited to specific buckets and cannot list them. Otherwise leave empty.',
                    )
                }}
            </p>
            <InputError :message="errors.buckets ?? errors['buckets.0']" />
        </div>

        <div class="grid gap-2">
            <Label>{{ $t('Supported features') }}</Label>
            <div class="grid gap-2 sm:grid-cols-3">
                <Label
                    v-for="option in capabilityOptions"
                    :key="option.value"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox
                        :model-value="form.capabilities.includes(option.value)"
                        :data-test="`capability-${option.value}`"
                        @update:model-value="
                            (checked) => toggleCapability(option.value, checked)
                        "
                    />
                    <span>{{ option.label }}</span>
                </Label>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Filled in from the provider defaults. Change if your storage supports a different set: unsupported features are hidden from the interface.',
                    )
                }}
            </p>
        </div>

        <div
            v-if="testResult"
            class="flex gap-3 rounded-lg border p-4 text-sm"
            :class="
                testResult.ok
                    ? 'border-green-600/40 bg-green-600/10'
                    : 'border-red-600/40 bg-red-600/10'
            "
            data-test="connection-result"
        >
            <CircleCheck
                v-if="testResult.ok"
                class="size-5 shrink-0 text-green-600"
            />
            <CircleX v-else class="size-5 shrink-0 text-red-600" />
            <div class="flex flex-col gap-1">
                <p>{{ testResult.message }}</p>
                <p
                    v-if="testResult.ok && testResult.bucketCount !== null"
                    class="text-muted-foreground"
                >
                    {{
                        $t('Buckets: :count', {
                            count: String(testResult.bucketCount),
                        })
                    }}<template v-if="testResult.buckets.length > 0">
                        — {{ testResult.buckets.join(', ') }}</template
                    >
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                :disabled="testing"
                data-test="test-connection-button"
                @click="testConnection"
            >
                <Spinner v-if="testing" />
                {{ $t('Test connection') }}
            </Button>
            <Button
                type="submit"
                :disabled="processing"
                data-test="save-storage-button"
            >
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>
        </div>
    </form>
</template>
