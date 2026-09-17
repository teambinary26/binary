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
