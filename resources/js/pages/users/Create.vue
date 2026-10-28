<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/User/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'New user', href: create() },
        ],
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="$t('New user')" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="$t('New user')"
            :description="
                $t(
                    'The user will sign in with a temporary password and will have to change it.',
                )
            "
        />

        <Form
            v-bind="UserController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input id="name" name="name" required v-focus />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ $t('Temporary password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <p class="text-sm text-muted-foreground">
                    {{ $t('Leave empty to generate a password.') }}
                </p>
                <InputError :message="errors.password" />
            </div>

            <Button :disabled="processing" data-test="create-user-button">
                {{ $t('Create') }}
            </Button>
        </Form>
    </div>
</template>
