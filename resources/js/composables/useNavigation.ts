import { usePage } from '@inertiajs/vue3';
import { LayoutGrid, Users } from '@lucide/vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { dashboard } from '@/routes';
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

        return items;
    });

    return { mainNavItems };
}
