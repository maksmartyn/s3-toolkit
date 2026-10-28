<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PasswordChangeController from '@/actions/App/Http/Controllers/Auth/PasswordChangeController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: 'Change password',
        description:
            'You signed in with a temporary password. Set a new password to continue.',
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="$t('Change password')" />

    <Form
        v-bind="PasswordChangeController.update.form()"
        reset-on-error
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="password">{{ $t('New password') }}</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                v-focus
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{
                $t('Confirm password')
            }}</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="change-password-button"
        >
            <Spinner v-if="processing" />
            {{ $t('Save') }}
        </Button>

        <Link
            :href="logout()"
            as="button"
            class="text-center text-sm text-muted-foreground underline-offset-4 hover:underline"
        >
            {{ $t('Log out') }}
        </Link>
    </Form>
</template>
