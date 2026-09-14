<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    siteKey: { type: String, default: '' },
    theme: { type: String, default: 'light' },
    size: { type: String, default: 'flexible' },
});

const emit = defineEmits(['verified', 'expired', 'error']);

const container = ref(null);
const widgetId = ref(null);
const loading = ref(true);
const errored = ref(false);

const SCRIPT_ID = 'cf-turnstile-script';
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let readyPromise = null;
let rendering = false;

const waitForTurnstile = () => {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('Window is not available.'));
    }

    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    if (readyPromise) {
        return readyPromise;
    }

    readyPromise = new Promise((resolve, reject) => {
        const done = () => {
            if (window.turnstile) {
                resolve(window.turnstile);
                return true;
            }
            return false;
        };

        const existing = document.getElementById(SCRIPT_ID)
            || document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]');

        if (! existing) {
            const script = document.createElement('script');
            script.id = SCRIPT_ID;
            script.src = SCRIPT_SRC;
            script.async = true;
            script.defer = true;
            script.onload = () => done() || reject(new Error('Turnstile loaded without a widget API.'));
            script.onerror = () => reject(new Error('Failed to load the Turnstile script.'));
            document.head.appendChild(script);
        } else if (! done()) {
            existing.addEventListener('load', () => done() || reject(new Error('Turnstile loaded without a widget API.')));
            existing.addEventListener('error', () => reject(new Error('Failed to load the Turnstile script.')));
        }

        let attempts = 0;
        const poll = window.setInterval(() => {
            attempts += 1;
            if (done() || attempts >= 80) {
                window.clearInterval(poll);
                if (! window.turnstile) {
                    readyPromise = null;
                    reject(new Error('Timed out waiting for Cloudflare Turnstile.'));
                }
            }
        }, 100);
    });

    return readyPromise;
};

const renderWidget = async ({ force = false } = {}) => {
    if (! props.siteKey || rendering) {
        if (! props.siteKey) {
            loading.value = false;
            errored.value = true;
        }
        return;
    }

    if (widgetId.value !== null && ! force) {
        return;
    }

    rendering = true;
    loading.value = true;
    errored.value = false;

    await nextTick();

    try {
        const turnstile = await waitForTurnstile();

        if (widgetId.value !== null) {
            try { turnstile.remove(widgetId.value); } catch (_) { /* noop */ }
            widgetId.value = null;
        }

        if (! container.value) {
            throw new Error('The verification box could not be created.');
        }

        widgetId.value = turnstile.render(container.value, {
            sitekey: props.siteKey,
            theme: props.theme,
            size: props.size,
            retry: 'auto',
            'retry-interval': 8000,
            'refresh-expired': 'auto',
            callback: (token) => {
                loading.value = false;
                errored.value = false;
                emit('verified', token);
            },
            'expired-callback': () => emit('expired'),
            'error-callback': () => {
                // 600010 is Cloudflare's generic failure. The widget retries itself;
                // do not tear it down or the challenge cannot recover.
                emit('error');
            },
            'timeout-callback': () => emit('expired'),
        });
        loading.value = false;
    } catch (error) {
        errored.value = true;
        loading.value = false;
        emit('error', error);
    } finally {
        rendering = false;
    }
};

const reset = () => {
    if (window.turnstile && widgetId.value !== null) {
        try {
            window.turnstile.reset(widgetId.value);
            errored.value = false;
            return;
        } catch (_) { /* fall through to a fresh render */ }
    }

    renderWidget({ force: true });
};

defineExpose({ reset, retry: reset });

onMounted(() => {
    renderWidget();
});

onBeforeUnmount(() => {
    if (window.turnstile && widgetId.value !== null) {
        try { window.turnstile.remove(widgetId.value); } catch (_) { /* noop */ }
    }
});
</script>

<template>
    <div class="flex w-full max-w-[300px] flex-col items-center gap-2">
        <div ref="container" class="cf-turnstile-box w-full min-h-[65px] bg-white" />
        <p v-if="loading" class="text-center text-xs text-gov-muted">Loading Cloudflare verification…</p>
        <p v-if="errored" class="text-center text-xs text-gov-danger">
            Cloudflare could not start. Please refresh this page and try again.
        </p>
        <button
            v-if="errored"
            class="btn-secondary btn-sm"
            type="button"
            @click="reset"
        >
            Retry verification
        </button>
    </div>
</template>
