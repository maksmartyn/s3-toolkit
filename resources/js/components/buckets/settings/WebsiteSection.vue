<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { reactive, watch } from 'vue';
import BucketSettingsController from '@/actions/App/Http/Controllers/Bucket/BucketSettingsController';
import SectionError from '@/components/buckets/settings/SectionError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import JsonEditor from '@/components/JsonEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useSectionSave } from '@/composables/useSectionSave';
import type { BucketSection, BucketWebsite, Storage } from '@/types';

const props = defineProps<{
    storage: Storage;
    bucket: string;
    section?: BucketSection<BucketWebsite | null> | null;
    canManage: boolean;
}>();

const form = reactive({
    enabled: false,
    mode: 'index' as 'index' | 'redirect',
    index_document: 'index.html',
    error_document: '',
    redirect_host: '',
    redirect_protocol: '',
    routing_rules: '',
});

watch(
    () => props.section?.data,
    (website) => {
        form.enabled = !!website;
        form.mode = website?.redirectHost ? 'redirect' : 'index';
        form.index_document = website?.indexDocument ?? 'index.html';
        form.error_document = website?.errorDocument ?? '';
        form.redirect_host = website?.redirectHost ?? '';
        form.redirect_protocol = website?.redirectProtocol ?? '';
        form.routing_rules = website?.routingRules ?? '';
    },
    { immediate: true },
);

const { processing, errors, save } = useSectionSave();

function submit(): void {
    void save(
        BucketSettingsController.website.url({
            storage: props.storage.id,
            bucket: props.bucket,
        }),
        { ...form },
    );
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <Heading
            variant="small"
            :title="$t('Static website hosting')"
            :description="
                $t(
                    'Serve the bucket contents as a website. Objects must also be publicly readable (policy or ACL).',
                )
            "
        />

        <Skeleton v-if="section === undefined" class="h-40 w-full" />
        <SectionError v-else-if="section?.error" :message="section.error" />
        <form
            v-else-if="section"
            class="flex max-w-xl flex-col gap-4"
            @submit.prevent="submit"
        >
            <a
                v-if="section.data?.url"
                :href="section.data.url"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-1 self-start font-mono text-sm underline-offset-4 hover:underline"
            >
                {{ section.data.url }}
                <ExternalLink class="size-3" />
            </a>

            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    v-model="form.enabled"
                    :disabled="!canManage"
                    data-test="website-enabled"
                />
                {{ $t('Hosting enabled') }}
            </Label>

            <template v-if="form.enabled">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.mode"
                            type="radio"
                            value="index"
                            :disabled="!canManage"
                        />
                        {{ $t('Serve a website') }}
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.mode"
                            type="radio"
                            value="redirect"
                            :disabled="!canManage"
                        />
                        {{ $t('Redirect all requests') }}
                    </label>
                </div>

                <template v-if="form.mode === 'index'">
                    <div class="grid gap-2">
                        <Label for="index-document">{{
                            $t('Index document')
                        }}</Label>
                        <Input
                            id="index-document"
                            v-model="form.index_document"
                            :disabled="!canManage"
                            class="font-mono"
                        />
                        <InputError :message="errors.index_document" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="error-document">{{
                            $t('Error document (optional)')
                        }}</Label>
                        <Input
                            id="error-document"
                            v-model="form.error_document"
                            :disabled="!canManage"
                            class="font-mono"
                            :placeholder="'404.html'"
                        />
                        <InputError :message="errors.error_document" />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('Routing rules (JSON, optional)')
                        }}</Label>
                        <JsonEditor
                            v-model="form.routing_rules"
                            :rows="6"
                            :disabled="!canManage"
                        />
                        <InputError :message="errors.routing_rules" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="redirect-host">{{ $t('Host name') }}</Label>
                        <Input
                            id="redirect-host"
                            v-model="form.redirect_host"
                            :disabled="!canManage"
                            class="font-mono"
                            :placeholder="'example.com'"
                        />
                        <InputError :message="errors.redirect_host" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="redirect-protocol">{{
                            $t('Protocol')
                        }}</Label>
                        <select
                            id="redirect-protocol"
                            v-model="form.redirect_protocol"
                            :disabled="!canManage"
                            class="w-40 rounded-md border border-input bg-transparent px-2 py-1.5 text-sm"
                        >
                            <option value="">
                                {{ $t('Same as request') }}
                            </option>
                            <option value="https">https</option>
                            <option value="http">http</option>
                        </select>
                    </div>
                </template>
            </template>

            <Button
                v-if="canManage"
                type="submit"
                class="self-start"
                :disabled="processing"
                data-test="save-website"
            >
                <Spinner v-if="processing" />
                {{ $t('Save') }}
            </Button>
        </form>
    </div>
</template>
