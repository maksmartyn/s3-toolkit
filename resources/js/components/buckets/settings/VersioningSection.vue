<script setup lang="ts">
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import type { BucketSection, Storage, VersioningStatus } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<VersioningStatus> | null;
    canManage: boolean;
}>();

const { processing, save } = useSectionSave();

const labels: Record<VersioningStatus, string> = {
    unversioned: 'Not enabled',
    enabled: 'Enabled',
    suspended: 'Suspended',
};

function setStatus(status: 'enabled' | 'suspended'): void {
    void save(
        BucketSettingsController.versioning.url({
            storage: props.storage.id,
            bucket: props.bucket,
        }),
        { status },
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('Versioning')"
            :description="
                $t(
                    'Keeps every version of an object, so overwritten and deleted objects can be restored. Once enabled, versioning can only be suspended, not turned off.',
                )
            "
        />

        <Skeleton v-if="section === undefined" class="h-10 w-64" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <div v-else-if="section" class="flex flex-wrap items-center gap-3">
            <Badge
                :variant="section.data === 'enabled' ? 'default' : 'secondary'"
                data-test="versioning-status"
                >{{ $t(labels[section.data]) }}</Badge
            >
            <template v-if="canManage">
                <Button
                    v-if="section.data !== 'enabled'"
                    :disabled="processing"
                    data-test="enable-versioning"
                    @click="setStatus('enabled')"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Enable') }}
                </Button>
                <Button
                    v-else
                    variant="outline"
                    :disabled="processing"
                    data-test="suspend-versioning"
                    @click="setStatus('suspended')"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Suspend') }}
                </Button>
            </template>
        </div>
    </div>
</template>
