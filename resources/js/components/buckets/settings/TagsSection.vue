<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import type { BucketSection, BucketTag, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<BucketTag[]> | null;
    canManage: boolean;
}>();

const tags = ref<BucketTag[]>([]);

watch(
    () => props.section?.data,
    (data) => (tags.value = (data ?? []).map((tag) => ({ ...tag }))),
    { immediate: true },
);

const { processing, errors, save } = useSectionSave();

function submit(): void {
    void save(
        BucketSettingsController.tags.url({
            storage: props.storage.id,
            bucket: props.bucket,
        }),
        { tags: tags.value.filter((tag) => tag.key.trim() !== '') },
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('Tags')"
            :description="
                $t('Up to 50 key–value pairs, for example for cost allocation.')
            "
        />

        <Skeleton v-if="section === undefined" class="h-24 w-full" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <form
            v-else-if="section"
            class="flex flex-col gap-3"
            @submit.prevent="submit"
        >
            <div
                v-for="(tag, index) in tags"
                :key="index"
                class="flex flex-col gap-1"
            >
                <div class="flex gap-2">
                    <Input
                        v-model="tag.key"
                        :placeholder="$t('Key')"
                        :disabled="!canManage"
                        class="font-mono"
                        :data-test="`tag-key-${index}`"
                    />
                    <Input
                        v-model="tag.value"
                        :placeholder="$t('Value')"
                        :disabled="!canManage"
                        class="font-mono"
                        :data-test="`tag-value-${index}`"
                    />
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove')"
                        @click="tags.splice(index, 1)"
                    >
                        <X />
                    </Button>
                </div>
                <InputError
                    :message="
                        errors[`tags.${index}.key`] ??
                        errors[`tags.${index}.value`]
                    "
                />
            </div>
            <p v-if="tags.length === 0" class="text-sm text-muted-foreground">
                {{ $t('No tags.') }}
            </p>
            <InputError :message="errors.tags" />

            <div v-if="canManage" class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="tags.length >= 50"
                    data-test="add-tag"
                    @click="tags.push({ key: '', value: '' })"
                >
                    <Plus />
                    {{ $t('Add tag') }}
                </Button>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-tags"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
