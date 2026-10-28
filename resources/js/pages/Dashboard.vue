<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <Heading
            :title="$t('Welcome, :name!', { name: user.name })"
            :description="
                $t(
                    'Storage management will appear here as it becomes available.',
                )
            "
        />
    </div>
</template>
