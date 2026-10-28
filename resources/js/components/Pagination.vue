<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

const props = defineProps<{
    paginator: Paginated<unknown>;
}>();

// Первая и последняя ссылки — «назад» и «вперёд», их рисуем иконками
const pages = computed(() => props.paginator.links.slice(1, -1));
const prev = computed(() => props.paginator.links[0]?.url ?? null);
const next = computed(() => props.paginator.links.at(-1)?.url ?? null);
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="flex items-center justify-between gap-4"
        :aria-label="$t('Pagination')"
    >
        <p class="text-sm text-muted-foreground">
            {{
                $t(':from–:to of :total', {
                    from: String(paginator.from ?? 0),
                    to: String(paginator.to ?? 0),
                    total: String(paginator.total),
                })
            }}
        </p>

        <div class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon"
                :disabled="!prev"
                :as-child="!!prev"
                :aria-label="$t('Previous')"
            >
                <Link v-if="prev" :href="prev" preserve-scroll>
                    <ChevronLeft />
                </Link>
                <ChevronLeft v-else />
            </Button>

            <template v-for="(link, index) in pages" :key="index">
                <span
                    v-if="!link.url"
                    class="px-2 text-sm text-muted-foreground"
                    >…</span
                >
                <Button
                    v-else
                    :variant="link.active ? 'default' : 'outline'"
                    size="icon"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>{{
                        link.label
                    }}</Link>
                </Button>
            </template>

            <Button
                variant="outline"
                size="icon"
                :disabled="!next"
                :as-child="!!next"
                :aria-label="$t('Next')"
            >
                <Link v-if="next" :href="next" preserve-scroll>
                    <ChevronRight />
                </Link>
                <ChevronRight v-else />
            </Button>
        </div>
    </nav>
</template>
