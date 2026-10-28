<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
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
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/audit-log';
import { edit as editUser } from '@/routes/users';
import type {
    AuditEntry,
    AuditFilters,
    Paginated,
    SelectOption,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit log', href: index() }],
    },
});

const props = defineProps<{
    entries: Paginated<AuditEntry>;
    filters: AuditFilters;
    actions: SelectOption[];
    can: { viewUsers: boolean };
}>();

const locale = usePage().props.locale;

const ALL = 'all';
const search = ref(props.filters.search ?? '');
const action = ref<string>(props.filters.action ?? ALL);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const userId = ref<number | null>(props.filters.user_id);

function applyFilters(): void {
    router.get(
        index.url({
            query: {
                search: search.value || undefined,
                action: action.value === ALL ? undefined : action.value,
                user_id: userId.value ?? undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, useDebounceFn(applyFilters, 300));
watch([action, dateFrom, dateTo, userId], applyFilters);
</script>

<template>
    <Head :title="$t('Audit log')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Audit log')"
            :description="
                $t(
                    'Changes to users, permissions and storages. Entries cannot be edited or deleted.',
                )
            "
        />

        <div class="flex flex-wrap items-center gap-2">
            <Input
                v-model="search"
                type="search"
                class="max-w-xs"
                :placeholder="$t('Search by actor or object')"
            />
            <Select v-model="action">
                <SelectTrigger class="w-64">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">{{
                        $t('All actions')
                    }}</SelectItem>
                    <SelectItem
                        v-for="option in actions"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Input
                v-model="dateFrom"
                type="date"
                class="w-40"
                :aria-label="$t('From')"
            />
            <span class="text-muted-foreground">—</span>
            <Input
                v-model="dateTo"
                type="date"
                class="w-40"
                :aria-label="$t('To')"
            />
            <Button
                v-if="userId !== null"
                variant="secondary"
                size="sm"
                @click="userId = null"
            >
                {{ $t('User #:id', { id: String(userId) }) }}
                <X />
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium whitespace-nowrap">
                            {{ $t('Time') }}
                        </th>
                        <th class="px-4 py-2 font-medium">{{ $t('Actor') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Action') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Object') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Details') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in entries.data"
                        :key="entry.id"
                        class="border-t align-top"
                        data-test="audit-entry"
                    >
                        <td
                            class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatDateTime(entry.createdAt, locale) }}
                        </td>
                        <td class="px-4 py-2">
                            <template v-if="entry.actorId !== null">
                                <div>{{ entry.actorName }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ entry.actorEmail }}
                                </div>
                                <div
                                    v-if="entry.ip"
                                    class="text-xs text-muted-foreground"
                                    :title="entry.userAgent ?? undefined"
                                >
                                    {{ entry.ip }}
                                </div>
                            </template>
                            <span v-else class="text-muted-foreground">{{
                                $t('System process')
                            }}</span>
                        </td>
                        <td class="px-4 py-2">{{ entry.actionLabel }}</td>
                        <td class="px-4 py-2">
                            <Link
                                v-if="
                                    entry.subjectType === 'user' &&
                                    entry.subjectId !== null &&
                                    can.viewUsers
                                "
                                :href="editUser(entry.subjectId)"
                                class="underline-offset-4 hover:underline"
                                >{{ entry.subjectLabel }}</Link
                            >
                            <span v-else>{{ entry.subjectLabel ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <ul
                                v-if="entry.lines.length > 0"
                                class="flex flex-col gap-0.5"
                            >
                                <li v-for="(line, i) in entry.lines" :key="i">
                                    {{ line }}
                                </li>
                            </ul>
                        </td>
                    </tr>
                    <tr v-if="entries.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ $t('No entries found.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="entries" />
    </div>
</template>
