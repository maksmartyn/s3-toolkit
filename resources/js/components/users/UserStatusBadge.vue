<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import type { User } from '@/types';

const props = defineProps<{
    user: Pick<User, 'status' | 'isSuperadmin'>;
}>();

const status = computed<{ label: string; variant: BadgeVariants['variant'] }>(
    () => {
        switch (props.user.status) {
            case 'blocked':
                return { label: 'Blocked', variant: 'destructive' };
            case 'deleted':
                return { label: 'Deleted', variant: 'outline' };
            default:
                return { label: 'Active', variant: 'secondary' };
        }
    },
);
</script>

<template>
    <div class="flex flex-wrap gap-1">
        <Badge :variant="status.variant">{{ $t(status.label) }}</Badge>
        <Badge v-if="user.isSuperadmin">{{ $t('Superadmin') }}</Badge>
    </div>
</template>
