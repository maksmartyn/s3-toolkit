import { usePage } from '@inertiajs/vue3';
import { KeyRound, LayoutGrid, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { dashboard } from '@/routes';
import { index as permissionTemplatesIndex } from '@/routes/permission-templates';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

/**
 * Пункты главного меню с учётом прав текущего пользователя. title — ключ перевода.
 */
export function useNavigation(): { mainNavItems: ComputedRef<NavItem[]> } {
    const page = usePage();

    const mainNavItems = computed<NavItem[]>(() => {
        const can = page.props.auth.can;
        const items: NavItem[] = [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ];

        if (can.viewUsers) {
            items.push({ title: 'Users', href: usersIndex(), icon: Users });
        }

        if (can.viewPermissionTemplates) {
            items.push({
                title: 'Permission templates',
                href: permissionTemplatesIndex(),
                icon: KeyRound,
            });
        }

        return items;
    });

    return { mainNavItems };
}
