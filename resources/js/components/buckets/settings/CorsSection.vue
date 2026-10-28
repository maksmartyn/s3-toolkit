<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
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
    CORS_METHODS,
    corsFromJson,
    corsToJson,
    emptyCorsRule,
} from '@/lib/cors';
import type { BucketSection, CorsRule, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<CorsRule[]> | null;
    canManage: boolean;
}>();

const mode = ref<'form' | 'json'>('form');
const rules = ref<CorsRule[]>([]);
const json = ref('');
const conversionError = ref<string | null>(null);

watch(
    () => props.section?.data,
    (data) => {
        rules.value = (data ?? []).map((rule) => ({ ...rule }));
        json.value = corsToJson(rules.value);
    },
    { immediate: true },
);

watch(mode, (next) => {
    conversionError.value = null;

    if (next === 'json') {
        json.value = corsToJson(rules.value);

        return;
    }

    try {
        rules.value = corsFromJson(json.value);
    } catch (e) {
        conversionError.value =
            e instanceof SyntaxError
                ? trans('The JSON is invalid.')
                : trans((e as Error).message);
        mode.value = 'json';
    }
});

function toggleMethod(
    rule: CorsRule,
    method: string,
    checked: boolean | 'indeterminate',
): void {
    rule.allowedMethods = rule.allowedMethods.filter((item) => item !== method);

    if (checked === true) {
        rule.allowedMethods.push(method);
    }
}

const { processing, errors, save } = useSectionSave();
const url = () =>
    BucketSettingsController.cors.url({
        storage: props.storage.id,
        bucket: props.bucket,
    });

const toPayload = (list: CorsRule[]) =>
    list.map((rule) => ({
        id: rule.id,
        allowed_origins: rule.allowedOrigins,
        allowed_methods: rule.allowedMethods,
        allowed_headers: rule.allowedHeaders,
        expose_headers: rule.exposeHeaders,
        max_age_seconds: rule.maxAgeSeconds,
    }));

function submit(): void {
    conversionError.value = null;
    let list = rules.value;

    if (mode.value === 'json') {
        try {
            list = corsFromJson(json.value);
        } catch (e) {
            conversionError.value =
                e instanceof SyntaxError
                    ? trans('The JSON is invalid.')
                    : trans((e as Error).message);

            return;
        }
    }

    void save(url(), { rules: toPayload(list) });
}

const remove = () => save(url(), { rules: [] });

const originPlaceholder = 'https://example.com';

function ruleTitle(index: number): string {
    return trans('Rule :number', { number: String(index + 1) });
}

function ruleError(index: number): string | undefined {
    return Object.entries(errors.value).find(([key]) =>
        key.startsWith(`rules.${index}.`),
    )?.[1];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('CORS')"
                :description="
                    $t(
                        'Which websites may access the bucket directly from the browser, for example for uploads with presigned links.',
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
            <template v-if="mode === 'form'">
                <p
                    v-if="rules.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('CORS is not configured.') }}
                </p>

                <div
                    v-for="(corsRule, index) in rules"
                    :key="index"
                    class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                    data-test="cors-rule"
                >
                    <div
                        class="flex items-center justify-between gap-2 sm:col-span-2"
                    >
                        <span class="text-sm font-medium">{{
                            ruleTitle(index)
                        }}</span>
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
                        <Label>{{ $t('Allowed origins') }}</Label>
                        <LinesInput
                            v-model="corsRule.allowedOrigins"
                            :disabled="!canManage"
                            :placeholder="originPlaceholder"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Allowed methods') }}</Label>
                        <div class="flex flex-wrap gap-3">
                            <Label
                                v-for="method in CORS_METHODS"
                                :key="method"
                                class="flex items-center gap-2 font-mono text-xs font-normal"
                            >
                                <Checkbox
                                    :model-value="
                                        corsRule.allowedMethods.includes(method)
                                    "
                                    :disabled="!canManage"
                                    @update:model-value="
                                        (checked) =>
                                            toggleMethod(
                                                corsRule,
                                                method,
                                                checked,
                                            )
                                    "
                                />
                                {{ method }}
                            </Label>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Allowed headers') }}</Label>
                        <LinesInput
                            v-model="corsRule.allowedHeaders"
                            :disabled="!canManage"
                            placeholder="*"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Exposed headers') }}</Label>
                        <LinesInput
                            v-model="corsRule.exposeHeaders"
                            :disabled="!canManage"
                            placeholder="ETag"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Max age, seconds') }}</Label>
                        <Input
                            :model-value="corsRule.maxAgeSeconds ?? ''"
                            type="number"
                            min="0"
                            :disabled="!canManage"
                            @update:model-value="
                                (value) =>
                                    (corsRule.maxAgeSeconds =
                                        value === '' ? null : Number(value))
                            "
                        />
                    </div>

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
                    data-test="add-cors-rule"
                    @click="rules.push(emptyCorsRule())"
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
                    data-test="save-cors"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save') }}
                </Button>
                <ConfirmDialog
                    v-if="(section?.data ?? []).length > 0"
                    :title="trans('Remove the CORS configuration?')"
                    :description="
                        trans(
                            'Browsers will no longer be able to access the bucket from other websites.',
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
                        >{{ $t('Remove CORS') }}</Button
                    >
                </ConfirmDialog>
            </div>
        </form>
    </div>
</template>
