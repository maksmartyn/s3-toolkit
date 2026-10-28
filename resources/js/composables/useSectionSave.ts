import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Сохранение раздела настроек: PUT, ошибки валидации по полям, признак выполнения.
 */
export function useSectionSave(): {
    processing: Ref<boolean>;
    errors: Ref<Record<string, string>>;
    save: (url: string, data: Record<string, unknown>) => Promise<boolean>;
} {
    const processing = ref(false);
    const errors = ref<Record<string, string>>({});

    function save(
        url: string,
        data: Record<string, unknown>,
    ): Promise<boolean> {
        errors.value = {};

        return new Promise((resolve) => {
            let ok = false;

            router.put(url, data as never, {
                preserveScroll: true,
                onStart: () => (processing.value = true),
                onSuccess: () => (ok = true),
                onError: (e) => (errors.value = e),
                onFinish: () => {
                    processing.value = false;
                    resolve(ok);
                },
            });
        });
    }

    return { processing, errors, save };
}
