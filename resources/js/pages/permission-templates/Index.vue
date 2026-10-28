<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/permission-templates';
import type { PermissionScopeData, PermissionTemplate } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Permission templates', href: index() }],
    },
});

const props = defineProps<{
    templates: PermissionTemplate[];
    scopes: PermissionScopeData[];
}>();

function templatesOf(scope: PermissionScopeData): PermissionTemplate[] {
    return props.templates.filter((template) => template.scope === scope.value);
}

function labelsOf(
    template: PermissionTemplate,
    scope: PermissionScopeData,
): string[] {
    return scope.options
        .filter((option) => template.permissions.includes(option.value))
        .map((option) => option.label);
}
</script>

<template>
    <Head :title="$t('Permission templates')" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="$t('Permission templates')"
            :description="
                $t(
                    'Ready-made permission sets. When granted, the set is copied: changing a template does not affect permissions already granted.',
                )
            "
        />

        <section
            v-for="scope in scopes"
            :key="scope.value"
            class="flex flex-col gap-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Heading variant="small" :title="scope.label" />
                <Button variant="outline" as-child>
                    <Link :href="create({ query: { scope: scope.value } })">
                        <Plus />
                        {{ $t('Add template') }}
                    </Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="w-1/3 px-4 py-2 font-medium">
                                {{ $t('Name') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Permissions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="template in templatesOf(scope)"
                            :key="template.id"
                            class="border-t align-top hover:bg-muted/30"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="edit(template.id)"
                                    class="font-medium underline-offset-4 hover:underline"
                                    >{{ template.name }}</Link
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{
                                    labelsOf(template, scope).join(', ') ||
                                    $t('No permissions')
                                }}
                            </td>
                        </tr>
                        <tr v-if="templatesOf(scope).length === 0">
                            <td
                                colspan="2"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No templates yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
