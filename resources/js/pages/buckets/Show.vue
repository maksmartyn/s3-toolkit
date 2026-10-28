<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AclSection from '@/components/buckets/settings/AclSection.vue';
import CorsSection from '@/components/buckets/settings/CorsSection.vue';
import LifecycleSection from '@/components/buckets/settings/LifecycleSection.vue';
import ObjectLockSection from '@/components/buckets/settings/ObjectLockSection.vue';
import PolicySection from '@/components/buckets/settings/PolicySection.vue';
import TagsSection from '@/components/buckets/settings/TagsSection.vue';
import VersioningSection from '@/components/buckets/settings/VersioningSection.vue';
import WebsiteSection from '@/components/buckets/settings/WebsiteSection.vue';
import Heading from '@/components/Heading.vue';
import { formatBytes, formatDateTime, formatNumber } from '@/lib/format';
import { index, show as showStorage } from '@/routes/storages';
import type {
    BucketAcl,
    BucketObjectLock,
    BucketSection,
    BucketWebsite,
    LifecycleRule,
    BucketStats,
    BucketTag,
    CorsRule,
    Storage,
    VersioningStatus,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

const props = defineProps<{
    storage: Storage;
    bucket: string;
    stats: BucketStats | null;
    // undefined — раздел ещё загружается, null — хранилище не поддерживает функцию
    versioning?: BucketSection<VersioningStatus> | null;
    tags?: BucketSection<BucketTag[]> | null;
    policy?: BucketSection<string | null> | null;
    cors?: BucketSection<CorsRule[]> | null;
    lifecycle?: BucketSection<LifecycleRule[]> | null;
    acl?: BucketSection<BucketAcl> | null;
    website?: BucketSection<BucketWebsite | null> | null;
    objectLock?: BucketSection<BucketObjectLock> | null;
    can: {
        manageVersioning: boolean;
        manageTags: boolean;
        managePolicy: boolean;
        manageCors: boolean;
        manageLifecycle: boolean;
        manageAcl: boolean;
        manageWebsite: boolean;
        manageObjectLock: boolean;
    };
}>();

const locale = usePage().props.locale;

type Tab =
    | 'overview'
    | 'versioning'
    | 'tags'
    | 'policy'
    | 'cors'
    | 'lifecycle'
    | 'acl'
    | 'website'
    | 'objectLock';

const tabs = computed(() =>
    (
        [
            { value: 'overview', label: 'Overview', available: true },
            {
                value: 'versioning',
                label: 'Versioning',
                available: props.versioning !== null,
            },
            { value: 'tags', label: 'Tags', available: props.tags !== null },
            {
                value: 'policy',
                label: 'Bucket policy',
                available: props.policy !== null,
            },
            { value: 'cors', label: 'CORS', available: props.cors !== null },
            {
                value: 'lifecycle',
                label: 'Lifecycle rules',
                available: props.lifecycle !== null,
            },
            { value: 'acl', label: 'ACL', available: props.acl !== null },
            {
                value: 'website',
                label: 'Website',
                available: props.website !== null,
            },
            {
                value: 'objectLock',
                label: 'Object Lock',
                available: props.objectLock !== null,
            },
        ] as { value: Tab; label: string; available: boolean }[]
    ).filter((tab) => tab.available),
);

const initial = new URLSearchParams(window.location.search).get(
    'tab',
) as Tab | null;
const tab = ref<Tab>(
    tabs.value.some((item) => item.value === initial)
        ? (initial as Tab)
        : 'overview',
);

// Вкладка в адресе: после сохранения и обновления страницы остаёмся на ней
watch(tab, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});
</script>

<template>
    <Head :title="bucket" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-col gap-2">
            <Link
                :href="showStorage(storage.id)"
                class="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ storage.name }}
            </Link>
            <Heading :title="bucket" :description="storage.providerLabel" />
        </div>

        <nav class="flex flex-wrap gap-1 border-b" role="tablist">
            <button
                v-for="item in tabs"
                :key="item.value"
                type="button"
                role="tab"
                :aria-selected="tab === item.value"
                class="-mb-px border-b-2 px-3 py-2 text-sm transition-colors"
                :class="
                    tab === item.value
                        ? 'border-primary font-medium'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :data-test="`tab-${item.value}`"
                @click="tab = item.value"
            >
                {{ $t(item.label) }}
            </button>
        </nav>

        <section v-if="tab === 'overview'" class="flex flex-col gap-2">
            <Heading variant="small" :title="$t('Statistics')" />
            <dl
                v-if="stats"
                class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm"
            >
                <dt class="text-muted-foreground">{{ $t('Objects') }}</dt>
                <dd>{{ formatNumber(stats.objectCount, locale) }}</dd>
                <dt class="text-muted-foreground">{{ $t('Size') }}</dt>
                <dd>{{ formatBytes(stats.totalSize, locale) }}</dd>
                <template v-if="stats.versionCount !== null">
                    <dt class="text-muted-foreground">
                        {{ $t('All versions') }}
                    </dt>
                    <dd>
                        {{ formatNumber(stats.versionCount, locale) }},
                        {{ formatBytes(stats.versionsSize ?? 0, locale) }}
                    </dd>
                </template>
                <dt class="text-muted-foreground">{{ $t('Calculated') }}</dt>
                <dd>{{ formatDateTime(stats.calculatedAt, locale) }}</dd>
            </dl>
            <p v-else class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Statistics have not been calculated yet. Use “Calculate” in the bucket list.',
                    )
                }}
            </p>
        </section>

        <VersioningSection
            v-if="tab === 'versioning'"
            :storage="storage"
            :bucket="bucket"
            :section="versioning"
            :can-manage="can.manageVersioning"
        />
        <TagsSection
            v-if="tab === 'tags'"
            :storage="storage"
            :bucket="bucket"
            :section="tags"
            :can-manage="can.manageTags"
        />
        <PolicySection
            v-if="tab === 'policy'"
            :storage="storage"
            :bucket="bucket"
            :section="policy"
            :can-manage="can.managePolicy"
        />
        <CorsSection
            v-if="tab === 'cors'"
            :storage="storage"
            :bucket="bucket"
            :section="cors"
            :can-manage="can.manageCors"
        />
        <LifecycleSection
            v-if="tab === 'lifecycle'"
            :storage="storage"
            :bucket="bucket"
            :section="lifecycle"
            :can-manage="can.manageLifecycle"
        />
        <AclSection
            v-if="tab === 'acl'"
            :storage="storage"
            :bucket="bucket"
            :section="acl"
            :can-manage="can.manageAcl"
        />
        <WebsiteSection
            v-if="tab === 'website'"
            :storage="storage"
            :bucket="bucket"
            :section="website"
            :can-manage="can.manageWebsite"
        />
        <ObjectLockSection
            v-if="tab === 'objectLock'"
            :storage="storage"
            :bucket="bucket"
            :section="objectLock"
            :can-manage="can.manageObjectLock"
        />
    </div>
</template>
