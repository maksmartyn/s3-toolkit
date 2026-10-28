<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import JsonEditor from '@/components/JsonEditor.vue';
import LinesInput from '@/components/LinesInput.vue';
import ModeSwitch from '@/components/ModeSwitch.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import {
    buildPolicy,
    COMMON_ACTIONS,
    parsePolicy,
    POLICY_TEMPLATES,
} from '@/lib/policy';
import type { PolicyStatementForm } from '@/lib/policy';
import type { BucketSection, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<string | null> | null;
    canManage: boolean;
}>();

const mode = ref<'form' | 'json'>('form');
const json = ref('');
const statements = ref<PolicyStatementForm[]>([]);
const formUnsupported = ref<string | null>(null);
const conversionError = ref<string | null>(null);

function load(policy: string | null): void {
    json.value = policy ?? '';
    const parsed = parsePolicy(policy);

    if (parsed.ok) {
        statements.value = parsed.statements;
        formUnsupported.value = null;
    } else {
        statements.value = [];
        formUnsupported.value = parsed.reason;
        mode.value = 'json';
    }
}

watch(
    () => props.section?.data,
    (data) => {
        if (props.section && !props.section.error) {
            load(data ?? null);
        }
    },
    { immediate: true },
);

// Переключение режимов: форма → JSON всегда возможна, JSON → форма — только если политика помещается в форму
watch(mode, (next, previous) => {
    conversionError.value = null;

    if (next === 'json' && previous === 'form') {
        try {
            json.value = buildPolicy(statements.value) ?? '';
        } catch {
            conversionError.value = trans('A condition contains invalid JSON.');
            mode.value = 'form';
        }
    }

    if (next === 'form' && previous === 'json') {
        const parsed = parsePolicy(json.value);

        if (parsed.ok) {
            statements.value = parsed.statements;
            formUnsupported.value = null;
        } else {
            formUnsupported.value = parsed.reason;
            mode.value = 'json';
        }
    }
});

const hasPolicy = computed(() => (props.section?.data ?? null) !== null);

function addStatement(statement?: PolicyStatementForm): void {
    statements.value.push(
        statement ?? {
            sid: '',
            effect: 'Allow',
            principal: 'everyone',
            principals: [],
            actions: ['s3:GetObject'],
            resources: [`arn:aws:s3:::${props.bucket}/*`],
            condition: '',
        },
    );
}

function toggleAction(
    statement: PolicyStatementForm,
    action: string,
    checked: boolean | 'indeterminate',
): void {
    statement.actions = statement.actions.filter((item) => item !== action);

    if (checked === true) {
        statement.actions.push(action);
    }
}

function customActions(statement: PolicyStatementForm): string[] {
    return statement.actions.filter(
        (action) => !COMMON_ACTIONS.includes(action),
    );
}

function setCustomActions(
    statement: PolicyStatementForm,
    custom: string[],
): void {
    statement.actions = [
        ...statement.actions.filter((action) =>
            COMMON_ACTIONS.includes(action),
        ),
        ...custom,
    ];
}

const { processing, errors, save } = useSectionSave();
const url = () =>
    BucketSettingsController.policy.url({
        storage: props.storage.id,
        bucket: props.bucket,
    });

function submit(): void {
    conversionError.value = null;
    let policy: string | null;

    try {
        policy =
            mode.value === 'form' ? buildPolicy(statements.value) : json.value;
    } catch {
        conversionError.value = trans('A condition contains invalid JSON.');

        return;
    }

    void save(url(), { policy });
}

const remove = () => save(url(), { policy: null });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('Bucket policy')"
                :description="
                    $t(
                        'Grants or denies access to the bucket and its objects at the storage level, regardless of this service.',
                    )
                "
            />
            <ModeSwitch v-if="section && !section.error" v-model="mode" />
        </div>

        <Skeleton v-if="section === undefined" class="h-40 w-full" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <form
            v-else-if="section"
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <p
                v-if="formUnsupported && mode === 'json'"
                class="rounded-md bg-muted p-3 text-sm text-muted-foreground"
            >
                {{ $t(formUnsupported) }} {{ $t('Edit the policy as JSON.') }}
            </p>

            <template v-if="mode === 'form'">
                <p
                    v-if="statements.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('The bucket has no policy.') }}
                </p>

                <div
                    v-for="(statement, index) in statements"
                    :key="index"
                    class="flex flex-col gap-4 rounded-lg border p-4"
                    data-test="policy-statement"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <select
                                v-model="statement.effect"
                                :disabled="!canManage"
                                class="rounded-md border border-input bg-transparent px-2 py-1 text-sm"
                                :aria-label="$t('Effect')"
                            >
                                <option value="Allow">{{ $t('Allow') }}</option>
                                <option value="Deny">{{ $t('Deny') }}</option>
                            </select>
                            <Input
                                v-model="statement.sid"
                                :disabled="!canManage"
                                :placeholder="$t('Statement ID (optional)')"
                                class="h-8 w-56 font-mono text-xs"
                            />
                        </div>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('Remove')"
                            @click="statements.splice(index, 1)"
                        >
                            <X />
                        </Button>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Who') }}</Label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="statement.principal"
                                    type="radio"
                                    value="everyone"
                                    :disabled="!canManage"
                                />
                                {{ $t('Everyone (public)') }}
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="statement.principal"
                                    type="radio"
                                    value="aws"
                                    :disabled="!canManage"
                                />
                                {{ $t('Specific accounts') }}
                            </label>
                        </div>
                        <LinesInput
                            v-if="statement.principal === 'aws'"
                            v-model="statement.principals"
                            :disabled="!canManage"
                            :rows="2"
                            :placeholder="
                                $t(
                                    'Account or user identifiers (ARN), one per line',
                                )
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Actions') }}</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Label
                                v-for="action in COMMON_ACTIONS"
                                :key="action"
                                class="flex items-center gap-2 font-mono text-xs font-normal"
                            >
                                <Checkbox
                                    :model-value="
                                        statement.actions.includes(action)
                                    "
                                    :disabled="!canManage"
                                    @update:model-value="
                                        (checked) =>
                                            toggleAction(
                                                statement,
                                                action,
                                                checked,
                                            )
                                    "
                                />
                                {{ action }}
                            </Label>
                        </div>
                        <LinesInput
                            :model-value="customActions(statement)"
                            :disabled="!canManage"
                            :rows="2"
                            :placeholder="$t('Other actions, one per line')"
                            @update:model-value="
                                (custom) => setCustomActions(statement, custom)
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Resources') }}</Label>
                        <LinesInput
                            v-model="statement.resources"
                            :disabled="!canManage"
                            :rows="2"
                            :placeholder="`arn:aws:s3:::${bucket}/*`"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Condition (JSON, optional)') }}</Label>
                        <textarea
                            v-model="statement.condition"
                            :disabled="!canManage"
                            rows="3"
                            spellcheck="false"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs shadow-xs outline-none"
                        />
                    </div>
                </div>

                <div v-if="canManage" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="addStatement()"
                    >
                        <Plus />
                        {{ $t('Add statement') }}
                    </Button>
                    <Button
                        v-for="template in POLICY_TEMPLATES"
                        :key="template.label"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="addStatement(template.statement(bucket))"
                    >
                        <Plus />
                        {{ $t(template.label) }}
                    </Button>
                </div>
            </template>

            <JsonEditor v-else v-model="json" :disabled="!canManage" />

            <InputError :message="conversionError ?? errors.policy" />

            <div v-if="canManage" class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-policy"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save') }}
                </Button>
                <ConfirmDialog
                    v-if="hasPolicy"
                    :title="trans('Remove the bucket policy?')"
                    :description="
                        trans(
                            'Access granted by the policy (for example public access) will stop working.',
                        )
                    "
                    :confirm-label="trans('Remove')"
                    variant="destructive"
                    :on-confirm="remove"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        >{{ $t('Remove policy') }}</Button
                    >
                </ConfirmDialog>
            </div>
        </form>
    </div>
</template>
