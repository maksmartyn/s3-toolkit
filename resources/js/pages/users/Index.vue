<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UserStatusBadge from '@/components/users/UserStatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { create, edit, index } from '@/routes/users';
import type { Paginated, SelectOption, User, UserStatus } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const props = defineProps<{
    users: Paginated<User>;
    filters: { search: string | null; status: UserStatus | null };
    statuses: SelectOption<UserStatus>[];
}>();

const locale = usePage().props.locale;

const ALL = 'all';
const search = ref(props.filters.search ?? '');
const status = ref<string>(props.filters.status ?? ALL);

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                search: search.value || undefined,
                status: status.value === ALL ? undefined : status.value,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const applySearch = useDebounceFn(applyFilters, 300);

watch(search, applySearch);
watch(status, applyFilters);
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="$t('Users')"
                :description="$t('Accounts that can sign in to the service')"
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    {{ $t('Add user') }}
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Input
                v-model="search"
                type="search"
                class="max-w-xs"
                :placeholder="$t('Search by name or email')"
                data-test="users-search"
            />
            <Select v-model="status">
                <SelectTrigger class="w-56">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{
                        $t('All except deleted')
                    }}</SelectItem>
                    <SelectItem
                        v-for="option in statuses"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-2 font-medium">{{ $t('Email') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Status') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Last login') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="edit(user.id)"
                                class="font-medium underline-offset-4 hover:underline"
                                >{{ user.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-2">{{ user.email }}</td>
                        <td class="px-4 py-2">
                            <UserStatusBadge :user="user" />
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                formatDateTime(user.lastLoginAt, locale) ??
                                $t('Never')
                            }}
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No users found.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" />
    </div>
</template>
