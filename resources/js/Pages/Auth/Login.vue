<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post(route('login.store'));
</script>

<template>
    <AuthLayout>
        <Head title="Sign in" />
        <div class="panel">
            <div class="panel-h">Authorized Access</div>
            <div class="panel-body">
                <p class="mb-4 text-sm text-gov-muted">Registered applicants and authorized municipal personnel may sign in to the Local Youth Development Office of Nabua.</p>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email">Email address</label>
                        <input id="email" v-model="form.email" type="email" required autocomplete="username">
                        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <PasswordInput id="password" v-model="form.password" autocomplete="current-password" required />
                    </div>
                    <label class="flex items-start gap-2 text-sm font-normal normal-case tracking-normal text-gov-text">
                        <input v-model="form.remember" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0">
                        <span>Remember this session</span>
                    </label>
                    <button class="btn-primary w-full" type="submit" :disabled="form.processing">Sign in</button>
                </form>
                <p class="mt-4 text-sm">No account yet? <Link :href="route('register')">Register as an applicant</Link></p>
                <div class="mt-5 border-t border-gov-border pt-4 text-xs text-gov-muted">
                    <p class="font-bold uppercase tracking-wide text-gov-dark">Demonstration accounts</p>
                    <p class="mt-1">Password for all demo accounts: <strong>Password123!</strong></p>
                    <ul class="mt-2 space-y-0.5 break-all sm:break-normal">
                        <li>admin@nabua.gov.ph — Administrator</li>
                        <li>staff@nabua.gov.ph — Staff</li>
                        <li>sk@nabua.gov.ph — Sangguniang Kabataan</li>
                        <li>juan.delacruz@email.com — Applicant</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthLayout>
</template>
