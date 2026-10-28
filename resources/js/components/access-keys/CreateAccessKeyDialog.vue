<script setup lang="ts">
import { Copy, Plus, TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { toast } from 'vue-sonner';
import { reactive, ref } from 'vue';
import AccessKeyController from '@/actions/App/Http/Controllers/Storage/AccessKeyController';
import InputError from '@/components/InputError.vue';
import JsonEditor from '@/components/JsonEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { HttpValidationError, requestJson } from '@/lib/http';
import type { AccessKeyFeatures, AccessKeySecret } from '@/types';

const props = defineProps<{
    storage: number;
    features: AccessKeyFeatures;
    users: string[];
    user: string | null;
}>();

const emit = defineEmits<{ created: [] }>();

const open = ref(false);
const form = reactive({
    name: '',
    description: '',
    user: '',
    expires_at: '',
    policy: '',
});
const errors = ref<Record<string, string>>({});
const processing = ref(false);
// Секрет показывается один раз: после закрытия диалога его больше не увидеть
const secret = ref<AccessKeySecret | null>(null);

function reset(): void {
    form.name = '';
    form.description = '';
    form.user = props.user ?? '';
    form.expires_at = '';
    form.policy = '';
    errors.value = {};
    secret.value = null;
}

async function submit(): Promise<void> {
    errors.value = {};
    processing.value = true;

    try {
        secret.value = await requestJson<AccessKeySecret>(
            'POST',
            AccessKeyController.store.url(props.storage),
            {
                ...form,
                expires_at: form.expires_at
                    ? new Date(form.expires_at).toISOString()
                    : null,
            },
        );
        emit('created');
    } catch (e) {
        if (e instanceof HttpValidationError) {
            errors.value = e.errors;
        } else {
            toast.error((e as Error).message);
        }
    } finally {
        processing.value = false;
    }
}

async function copy(value: string): Promise<void> {
    await navigator.clipboard.writeText(value);
    toast.success(trans('Copied.'));
}
</script>

<template>
    <Dialog
        v-model:open="open"
        @update:open="(value: boolean) => value && reset()"
    >
        <DialogTrigger as-child>
            <Button data-test="create-key">
                <Plus />
                {{ $t('Create key') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <template v-if="secret">
                <DialogHeader>
                    <DialogTitle>{{ $t('Key created') }}</DialogTitle>
                    <DialogDescription class="flex gap-2">
                        <TriangleAlert class="size-4 shrink-0 text-amber-500" />
                        {{
                            $t(
                                'Copy the secret key now. It is not stored anywhere and cannot be shown again.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label>{{ $t('Access key ID') }}</Label>
                    <div class="flex gap-2">
                        <Input
                            :model-value="secret.accessKeyId"
                            readonly
                            class="font-mono"
                            data-test="new-key-id"
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            :aria-label="$t('Copy')"
                            @click="copy(secret.accessKeyId)"
                        >
                            <Copy />
                        </Button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ $t('Secret key') }}</Label>
                    <div class="flex gap-2">
                        <Input
                            :model-value="secret.secretAccessKey"
                            readonly
                            class="font-mono"
                            data-test="new-key-secret"
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            :aria-label="$t('Copy')"
                            @click="copy(secret.secretAccessKey)"
                        >
                            <Copy />
                        </Button>
                    </div>
                </div>
                <DialogFooter>
                    <Button @click="open = false">{{ $t('Done') }}</Button>
                </DialogFooter>
            </template>

            <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('New access key') }}</DialogTitle>
                </DialogHeader>

                <div v-if="features.users" class="grid gap-2">
                    <Label for="key-user">{{ $t('User') }}</Label>
                    <select
                        id="key-user"
                        v-model="form.user"
                        class="rounded-md border border-input bg-transparent px-2 py-1.5 font-mono text-sm"
                    >
                        <option value="">
                            {{ $t('Key management account') }}
                        </option>
                        <option v-for="name in users" :key="name" :value="name">
                            {{ name }}
                        </option>
                    </select>
                    <InputError :message="errors.user" />
                </div>
                <div v-if="features.name" class="grid gap-2">
                    <Label for="key-name">{{ $t('Name') }}</Label>
                    <Input id="key-name" v-model="form.name" maxlength="32" />
                    <InputError :message="errors.name" />
                </div>
                <div v-if="features.description" class="grid gap-2">
                    <Label for="key-description">{{ $t('Description') }}</Label>
                    <Input
                        id="key-description"
                        v-model="form.description"
                        maxlength="256"
                    />
                    <InputError :message="errors.description" />
                </div>
                <div v-if="features.expiration" class="grid gap-2">
                    <Label for="key-expires">{{
                        $t('Expires (optional)')
                    }}</Label>
                    <Input
                        id="key-expires"
                        v-model="form.expires_at"
                        type="datetime-local"
                        class="w-56"
                    />
                    <InputError :message="errors.expires_at" />
                </div>
                <div v-if="features.policy" class="grid gap-2">
                    <Label>{{
                        $t('Restricting policy (JSON, optional)')
                    }}</Label>
                    <JsonEditor v-model="form.policy" :rows="6" />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Empty — the key has the same rights as its owner. A policy can only narrow them.',
                            )
                        }}
                    </p>
                    <InputError :message="errors.policy" />
                </div>

                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="create-key-submit"
                    >
                        <Spinner v-if="processing" />
                        {{ $t('Create') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
