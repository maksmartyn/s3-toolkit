<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import type { BucketAcl, BucketSection, CannedAcl, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<BucketAcl> | null;
    canManage: boolean;
}>();

const options: { value: CannedAcl; label: string; hint: string }[] = [
    { value: 'private', label: 'Private', hint: 'Only the owner has access.' },
    {
        value: 'public-read',
        label: 'Public read',
        hint: 'Anyone on the internet can list and read objects.',
    },
    {
        value: 'public-read-write',
        label: 'Public read and write',
        hint: 'Anyone on the internet can read, upload and delete objects. Almost never what you want.',
    },
    {
        value: 'authenticated-read',
        label: 'Read for any authenticated user',
        hint: 'Any account of this storage provider can read objects, not only yours.',
    },
];

const selected = ref<CannedAcl | null>(null);

watch(
    () => props.section?.data?.canned,
    (canned) => (selected.value = canned ?? null),
    { immediate: true },
);

const { processing, save } = useSectionSave();

function submit(): void {
    if (selected.value) {
        void save(
            BucketSettingsController.acl.url({
                storage: props.storage.id,
                bucket: props.bucket,
            }),
            { acl: selected.value },
        );
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('ACL and public access')"
            :description="
                $t(
                    'Access control list of the bucket. For fine-grained access prefer the bucket policy.',
                )
            "
        />

        <Skeleton v-if="section === undefined" class="h-40 w-full" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <form
            v-else-if="section"
            class="flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <p
                v-if="section.data.canned === null"
                class="rounded-md bg-muted p-3 text-sm text-muted-foreground"
            >
                {{
                    $t(
                        'The current grants were configured manually. Choosing a preset will replace them.',
                    )
                }}
            </p>

            <div class="flex flex-col gap-2">
                <label
                    v-for="option in options"
                    :key="option.value"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm"
                    :class="{ 'border-primary': selected === option.value }"
                >
                    <input
                        v-model="selected"
                        type="radio"
                        :value="option.value"
                        :disabled="!canManage"
                        class="mt-1"
                        :data-test="`acl-${option.value}`"
                    />
                    <span>
                        <span class="flex items-center gap-2 font-medium">
                            {{ $t(option.label) }}
                            <TriangleAlert
                                v-if="option.value === 'public-read-write'"
                                class="size-4 text-amber-500"
                            />
                        </span>
                        <span class="text-muted-foreground">{{
                            $t(option.hint)
                        }}</span>
                    </span>
                </label>
            </div>

            <details class="text-sm">
                <summary class="cursor-pointer text-muted-foreground">
                    {{ $t('Current grants') }}
                </summary>
                <table class="mt-2 w-full text-xs">
                    <tbody>
                        <tr
                            v-for="(grant, index) in section.data.grants"
                            :key="index"
                            class="border-t"
                        >
                            <td class="py-1 pr-4 font-mono break-all">
                                {{ grant.grantee }}
                            </td>
                            <td class="py-1 pr-4 text-muted-foreground">
                                {{ grant.type }}
                            </td>
                            <td class="py-1 font-mono">
                                {{ grant.permission }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>

            <Button
                v-if="canManage"
                type="submit"
                class="self-start"
                :disabled="
                    processing ||
                    selected === null ||
                    selected === section.data.canned
                "
                data-test="save-acl"
            >
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>
        </form>
    </div>
</template>
