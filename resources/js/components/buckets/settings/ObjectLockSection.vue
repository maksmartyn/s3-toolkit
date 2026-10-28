<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { reactive, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import type { BucketObjectLock, BucketSection, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<BucketObjectLock> | null;
    canManage: boolean;
}>();

const form = reactive({
    mode: '' as '' | 'GOVERNANCE' | 'COMPLIANCE',
    unit: 'days' as 'days' | 'years',
    period: 30 as number | string,
});

watch(
    () => props.section?.data,
    (lock) => {
        form.mode = lock?.mode ?? '';
        form.unit = lock?.years ? 'years' : 'days';
        form.period = lock?.years ?? lock?.days ?? 30;
    },
    { immediate: true },
);

const { processing, errors, save } = useSectionSave();

function submit(): Promise<boolean> {
    return save(
        BucketSettingsController.objectLock.url({
            storage: props.storage.id,
            bucket: props.bucket,
        }),
        form.mode === ''
            ? { mode: null }
            : { mode: form.mode, unit: form.unit, period: Number(form.period) },
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('Object Lock')"
            :description="
                $t(
                    'Protects object versions from deletion and overwriting for a retention period (WORM). Once enabled, Object Lock cannot be turned off.',
                )
            "
        />

        <Skeleton v-if="section === undefined" class="h-32 w-full" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <div v-else-if="section" class="flex max-w-xl flex-col gap-4">
            <Badge
                :variant="section.data.enabled ? 'default' : 'secondary'"
                class="self-start"
                data-test="object-lock-status"
                >{{
                    section.data.enabled ? $t('Enabled') : $t('Not enabled')
                }}</Badge
            >

            <div class="grid gap-2">
                <Label for="retention-mode">{{
                    $t('Default retention for new objects')
                }}</Label>
                <select
                    id="retention-mode"
                    v-model="form.mode"
                    :disabled="!canManage"
                    class="w-64 rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                >
                    <option value="">{{ $t('None') }}</option>
                    <option value="GOVERNANCE">
                        {{ $t('Governance') }}
                    </option>
                    <option value="COMPLIANCE">
                        {{ $t('Compliance') }}
                    </option>
                </select>
                <p class="text-sm text-muted-foreground">
                    {{
                        form.mode === 'COMPLIANCE'
                            ? $t(
                                  'Nobody, including the account owner, can delete protected versions until the period ends.',
                              )
                            : $t(
                                  'Users with a special permission can remove the protection.',
                              )
                    }}
                </p>
                <InputError :message="errors.mode" />
            </div>

            <div v-if="form.mode !== ''" class="flex gap-2">
                <Input
                    v-model="form.period"
                    type="number"
                    min="1"
                    class="w-32"
                    :disabled="!canManage"
                    :aria-label="$t('Period')"
                />
                <select
                    v-model="form.unit"
                    :disabled="!canManage"
                    class="rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                >
                    <option value="days">{{ $t('days') }}</option>
                    <option value="years">{{ $t('years') }}</option>
                </select>
            </div>
            <InputError :message="errors.period" />

            <template v-if="canManage">
                <Button
                    v-if="section.data.enabled"
                    class="self-start"
                    :disabled="processing"
                    data-test="save-object-lock"
                    @click="submit"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save') }}
                </Button>
                <ConfirmDialog
                    v-else
                    :title="trans('Enable Object Lock?')"
                    :description="
                        trans(
                            'Object Lock cannot be turned off later. Versioning must already be enabled.',
                        )
                    "
                    :confirm-label="trans('Enable')"
                    :on-confirm="submit"
                >
                    <Button class="self-start" data-test="enable-object-lock">{{
                        $t('Enable Object Lock')
                    }}</Button>
                </ConfirmDialog>
            </template>
        </div>
    </div>
</template>
