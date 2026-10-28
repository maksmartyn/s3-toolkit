<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import StorageController from '@/actions/App/Http/Controllers/Storage/StorageController';
import BucketSection from '@/components/buckets/BucketSection.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import StorageAccessManager from '@/components/storages/StorageAccessManager.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/storages';
import type {
    BucketList,
    Operation,
    PermissionOption,
    PermissionTemplate,
    Storage,
    StorageAccess,
    StorageDetails,
    User,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

const props = defineProps<{
    storage: Storage;
    details: StorageDetails | null;
    permissionOptions: PermissionOption[];
    accesses: StorageAccess[];
    candidates: User[];
    permissionTemplates: PermissionTemplate[];
    buckets?: BucketList | null;
    recentOperations?: Operation[];
    can: {
        update: boolean;
        delete: boolean;
        manageAccess: boolean;
        viewBuckets: boolean;
        createBuckets: boolean;
        deleteBuckets: boolean;
        viewObjects: boolean;
    };
}>();

function labels(permissions: number[]): string[] {
    return props.permissionOptions
        .filter((option) => permissions.includes(option.value))
        .map((option) => option.label);
}

function destroy(): Promise<void> {
    return new Promise((resolve) => {
        router.delete(StorageController.destroy.url(props.storage.id), {
            onFinish: () => resolve(),
        });
    });
}
</script>

<template>
    <Head :title="storage.name" />

    <div class="flex max-w-4xl flex-col gap-8 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="storage.name"
                :description="storage.providerLabel"
            />
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="StorageController.edit.url(storage.id)">{{
                        $t('Edit')
                    }}</Link>
                </Button>
                <ConfirmDialog
                    v-if="can.delete"
                    :title="trans('Delete storage?')"
                    :description="
                        trans(
                            'The storage account, its keys and all granted permissions will be removed from the service. Data in the storage itself is not affected.',
                        )
                    "
                    :confirm-label="trans('Delete')"
                    variant="destructive"
                    :on-confirm="destroy"
                >
                    <Button variant="destructive">{{ $t('Delete') }}</Button>
                </ConfirmDialog>
            </div>
        </div>

        <BucketSection
            v-if="can.viewBuckets"
            :storage="storage"
            :buckets="buckets"
            :recent-operations="recentOperations"
            :can="can"
        />

        <section class="flex flex-col gap-2">
            <Heading variant="small" :title="$t('My permissions')" />
            <p
                v-if="storage.permissions.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('You have no permissions on this storage.') }}
            </p>
            <p
                v-else-if="
                    storage.permissions.length === permissionOptions.length
                "
                class="text-sm"
            >
                {{ $t('All permissions') }}
            </p>
            <ul v-else class="list-disc pl-5 text-sm">
                <li v-for="label in labels(storage.permissions)" :key="label">
                    {{ label }}
                </li>
            </ul>
        </section>

        <section v-if="details" class="flex flex-col gap-2">
            <Heading variant="small" :title="$t('Connection')" />
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                <dt class="text-muted-foreground">{{ $t('Endpoint') }}</dt>
                <dd class="font-mono break-all">
                    {{ details.endpoint ?? $t('Default for the region') }}
                </dd>
                <dt class="text-muted-foreground">{{ $t('Region') }}</dt>
                <dd class="font-mono">{{ details.region }}</dd>
                <dt class="text-muted-foreground">{{ $t('Addressing') }}</dt>
                <dd>
                    {{
                        details.usePathStyle
                            ? $t('Path-style')
                            : $t('Virtual-hosted')
                    }}
                </dd>
                <dt class="text-muted-foreground">
                    {{ $t('Verify SSL certificate') }}
                </dt>
                <dd>{{ details.verifySsl ? $t('Yes') : $t('No') }}</dd>
                <dt class="text-muted-foreground">
                    {{ $t('Access key ID') }}
                </dt>
                <dd class="font-mono">{{ details.accessKeyIdMasked }}</dd>
                <template v-if="details.buckets">
                    <dt class="text-muted-foreground">{{ $t('Buckets') }}</dt>
                    <dd class="font-mono">{{ details.buckets.join(', ') }}</dd>
                </template>
            </dl>
        </section>

        <StorageAccessManager
            v-if="can.manageAccess"
            :storage="storage"
            :accesses="accesses"
            :candidates="candidates"
            :options="permissionOptions"
            :templates="permissionTemplates"
        />
    </div>
</template>
