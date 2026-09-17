<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import Turnstile from '@/Components/Turnstile.vue';

const page = usePage();
const turnstileSiteKey = computed(() => page.props.turnstile?.site_key || '');
const turnstileEnabled = computed(() => page.props.turnstile?.enabled !== false);
const turnstileRef = ref(null);
const googleError = computed(() => page.props.errors?.google || '');

const form = useForm({
    email: '',
    password: '',
    remember: false,
    turnstile_token: '',
});

const onTurnstileVerified = (token) => {
    form.turnstile_token = token;
    form.clearErrors('turnstile_token');
};

const onTurnstileExpired = () => {
    form.turnstile_token = '';
};

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }

    return ! turnstileEnabled.value || Boolean(form.turnstile_token);
});

const submit = () => {
    if (turnstileEnabled.value && ! form.turnstile_token) {
        form.setError('turnstile_token', 'Please complete the Cloudflare security check before signing in.');
        return;
    }

    form.post(route('login.store'), {
        onFinish: () => {
            form.turnstile_token = '';
            turnstileRef.value?.reset();
        },
    });
};
</script>

<template>
    <AuthLayout>
        <Head title="Sign in" />
        <div class="panel">
            <div class="panel-h">Authorized Access</div>
            <div class="panel-body">
                <p class="mb-4 text-sm text-gov-muted">Registered applicants and authorized municipal personnel may sign in to the Local Youth Development Office of Nabua.</p>
                <p v-if="googleError" class="field-error mb-4">{{ googleError }}</p>
                <a :href="route('login.google')" class="btn-secondary w-full">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    Continue with Google
                </a>
                <p class="mt-2 text-xs text-gov-muted">Google sign-in is for existing approved accounts only. It cannot be used to register.</p>
                <div class="my-4 flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-gov-muted">
                    <span class="h-px flex-1 bg-gov-border"></span>
                    <span>or</span>
                    <span class="h-px flex-1 bg-gov-border"></span>
                </div>
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
                    <div>
                        <p class="mb-2 text-xs text-gov-muted">Complete the Cloudflare security check to continue.</p>
                        <div v-if="turnstileEnabled" class="flex min-h-[65px] justify-center overflow-visible py-1">
                            <Turnstile
                                v-if="turnstileSiteKey"
                                ref="turnstileRef"
                                :site-key="turnstileSiteKey"
                                size="flexible"
                                @verified="onTurnstileVerified"
                                @expired="onTurnstileExpired"
                                @error="onTurnstileExpired"
                            />
                            <p v-else class="text-sm text-gov-danger">Cloudflare is not configured. Please contact the office.</p>
                        </div>
                        <p v-if="form.errors.turnstile_token" class="field-error">{{ form.errors.turnstile_token }}</p>
                    </div>
                    <button class="btn-primary w-full" type="submit" :disabled="!canSubmit">Sign in</button>
                </form>
                <p class="mt-4 text-sm">Need help? <Link :href="route('site.how-to-apply')">See how to apply</Link></p>
            </div>
        </div>
    </AuthLayout>
</template>
