<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/storages';
import type { Storage } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Storages', href: index() }],
    },
});

defineProps<{
    storages: Storage[];
    can: { create: boolean };
}>();
</script>

<template>
    <Head :title="$t('Storages')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Storages')"
                :description="$t('S3-compatible storage accounts')"
            />
            <Button v-if="can.create" as-child>
                <Link :href="create()">
                    <Plus />
                    {{ $t('Add storage') }}
                </Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Provider') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('My permissions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="storage in storages"
                        :key="storage.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="show(storage.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ storage.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-2">{{ storage.providerLabel }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                storage.permissions.length > 0
                                    ? $t('Permissions: :count', {
                                          count: String(
                                              storage.permissions.length,
                                          ),
                                      })
                                    : $t('No access')
                            }}
                        </td>
                    </tr>
                    <tr v-if="storages.length === 0">
                        <td
                            colspan="3"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No storages available.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
