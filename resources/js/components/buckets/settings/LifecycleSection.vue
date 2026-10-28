<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { nextTick, ref, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import JsonEditor from '@/components/JsonEditor.vue';
import ModeSwitch from '@/components/ModeSwitch.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import {
    emptyLifecycleRule,
    lifecycleFromForm,
    lifecycleToForm,
} from '@/lib/lifecycle';
import type { LifecycleRuleForm } from '@/lib/lifecycle';
import type { BucketSection, LifecycleRule, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<LifecycleRule[]> | null;
    canManage: boolean;
}>();

const mode = ref<'form' | 'json'>('form');
const rules = ref<LifecycleRuleForm[]>([]);
const json = ref('');
const formUnsupported = ref(false);
const conversionError = ref<string | null>(null);
// Смена режима при загрузке данных не должна запускать преобразование (оно затёрло бы загруженный JSON)
let loading = false;

watch(
    () => props.section?.data,
    (data) => {
        loading = true;
        const raw = data ?? [];
        json.value = raw.length > 0 ? JSON.stringify(raw, null, 2) : '';
        const form = lifecycleToForm(raw);
        formUnsupported.value = form === null;
        rules.value = form ?? [];
        mode.value = form === null ? 'json' : 'form';
        void nextTick(() => (loading = false));
    },
    { immediate: true },
);

function parseJson(): LifecycleRule[] {
    if (json.value.trim() === '') {
        return [];
    }

    const value: unknown = JSON.parse(json.value);
    const list =
        typeof value === 'object' && value !== null && 'Rules' in value
            ? (value as { Rules: unknown }).Rules
            : value;

    if (!Array.isArray(list)) {
        throw new Error('Lifecycle rules must be a JSON array.');
    }

    return list as LifecycleRule[];
}

watch(mode, (next) => {
    if (loading) {
        return;
    }

    conversionError.value = null;

    if (next === 'json') {
        json.value = rules.value.length
            ? JSON.stringify(lifecycleFromForm(rules.value), null, 2)
            : '';

        return;
    }

    try {
        const form = lifecycleToForm(parseJson());

        if (form === null) {
            formUnsupported.value = true;
            mode.value = 'json';

            return;
        }

        formUnsupported.value = false;
        rules.value = form;
    } catch (e) {
        conversionError.value =
            e instanceof SyntaxError
                ? trans('The JSON is invalid.')
                : trans((e as Error).message);
        mode.value = 'json';
    }
});

const number = (value: string | number) =>
    value === '' || value === null ? null : Number(value);

const { processing, errors, save } = useSectionSave();
const url = () =>
    BucketSettingsController.lifecycle.url({
        storage: props.storage.id,
        bucket: props.bucket,
    });

function submit(): void {
    conversionError.value = null;
    let payload: LifecycleRule[];

    try {
        payload =
            mode.value === 'form'
                ? lifecycleFromForm(rules.value)
                : parseJson();
    } catch (e) {
        conversionError.value =
            e instanceof SyntaxError
                ? trans('The JSON is invalid.')
                : trans((e as Error).message);

        return;
    }

    void save(url(), { rules: payload });
}

const remove = () => save(url(), { rules: [] });

function ruleError(index: number): string | undefined {
    return Object.entries(errors.value).find(([key]) =>
        key.startsWith(`rules.${index}`),
    )?.[1];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('Lifecycle rules')"
                :description="
                    $t(
                        'Automatically delete objects, old versions and unfinished uploads after a number of days. The storage applies the rules on its own, usually once a day.',
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
                {{
                    $t(
                        'The rules use elements the form does not support (for example storage class transitions or tag filters). Edit them as JSON.',
                    )
                }}
            </p>

            <template v-if="mode === 'form'">
                <p
                    v-if="rules.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('No lifecycle rules.') }}
                </p>

                <div
                    v-for="(lifecycleRule, index) in rules"
                    :key="index"
                    class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                    data-test="lifecycle-rule"
                >
                    <div
                        class="flex items-center justify-between gap-2 sm:col-span-2"
                    >
                        <Label class="flex items-center gap-2 font-normal">
                            <Checkbox
                                v-model="lifecycleRule.enabled"
                                :disabled="!canManage"
                            />
                            {{ $t('Rule enabled') }}
                        </Label>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="$t('Remove')"
                            @click="rules.splice(index, 1)"
                        >
                            <X />
                        </Button>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Rule ID') }}</Label>
                        <Input
                            v-model="lifecycleRule.id"
                            :disabled="!canManage"
                            class="font-mono"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ $t('Prefix (empty — whole bucket)') }}</Label>
                        <Input
                            v-model="lifecycleRule.prefix"
                            :disabled="!canManage"
                            class="font-mono"
                            :placeholder="$t('for example logs/')"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Delete objects after, days') }}</Label>
                        <Input
                            :model-value="lifecycleRule.expirationDays ?? ''"
                            type="number"
                            min="1"
                            :disabled="!canManage"
                            @update:model-value="
                                (value) =>
                                    (lifecycleRule.expirationDays =
                                        number(value))
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('Delete old versions after, days')
                        }}</Label>
                        <Input
                            :model-value="lifecycleRule.noncurrentDays ?? ''"
                            type="number"
                            min="1"
                            :disabled="!canManage"
                            @update:model-value="
                                (value) =>
                                    (lifecycleRule.noncurrentDays =
                                        number(value))
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('Abort unfinished uploads after, days')
                        }}</Label>
                        <Input
                            :model-value="lifecycleRule.abortDays ?? ''"
                            type="number"
                            min="1"
                            :disabled="!canManage"
                            @update:model-value="
                                (value) =>
                                    (lifecycleRule.abortDays = number(value))
                            "
                        />
                    </div>
                    <Label class="flex items-center gap-2 self-end font-normal">
                        <Checkbox
                            v-model="lifecycleRule.expiredDeleteMarker"
                            :disabled="
                                !canManage ||
                                lifecycleRule.expirationDays !== null
                            "
                        />
                        {{ $t('Remove expired delete markers') }}
                    </Label>

                    <InputError
                        class="sm:col-span-2"
                        :message="ruleError(index)"
                    />
                </div>

                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    class="self-start"
                    data-test="add-lifecycle-rule"
                    @click="rules.push(emptyLifecycleRule())"
                >
                    <Plus />
                    {{ $t('Add rule') }}
                </Button>
            </template>

            <JsonEditor v-else v-model="json" :disabled="!canManage" />

            <InputError :message="conversionError ?? errors.rules" />

            <div v-if="canManage" class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-lifecycle"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save') }}
                </Button>
                <ConfirmDialog
                    v-if="(section?.data ?? []).length > 0"
                    :title="trans('Remove all lifecycle rules?')"
                    :confirm-label="trans('Remove')"
                    variant="destructive"
                    :on-confirm="remove"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        >{{ $t('Remove rules') }}</Button
                    >
                </ConfirmDialog>
            </div>
        </form>
    </div>
</template>
