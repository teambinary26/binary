<script setup>
import { onUnmounted, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm action' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    tone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'danger', 'success', 'warning'].includes(value),
    },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const close = () => {
    if (props.processing) return;
    emit('cancel');
};

const confirmAction = () => {
    if (props.processing) return;
    emit('confirm');
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        confirmAction();
    }
};

const confirmButtonClass = () => ({
    primary: 'btn-primary',
    danger: 'btn-danger',
    success: 'btn-success',
    warning: 'btn-warning',
}[props.tone] || 'btn-primary');

const iconWrapperClass = () => ({
    primary: 'bg-gov-blue/10 text-gov-blue',
    danger: 'bg-gov-danger/10 text-gov-danger',
    success: 'bg-gov-success/10 text-gov-success',
    warning: 'bg-gov-warning/10 text-yellow-700',
}[props.tone] || 'bg-gov-blue/10 text-gov-blue');

watch(() => props.show, (open) => {
    if (open) {
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeydown);
    } else {
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[90] flex items-center justify-center p-4"
            role="presentation"
        >
            <div class="absolute inset-0 bg-gov-navy/60" @click="close" />
            <div
                class="relative z-10 flex w-full max-w-md flex-col border border-gov-border bg-white shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-modal-title"
            >
                <div class="flex items-start gap-3 border-b border-gov-border p-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="iconWrapperClass()">
                        <svg
                            v-if="tone === 'danger'"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <svg
                            v-else-if="tone === 'success'"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 id="confirm-modal-title" class="text-base font-bold uppercase tracking-wide text-gov-dark">
                            {{ title }}
                        </h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-gov-text">
                            <slot>{{ message }}</slot>
                        </p>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gov-border bg-gov-off px-4 py-3 sm:flex-row sm:justify-end sm:px-5">
                    <button type="button" class="btn-ghost btn-sm w-full sm:w-auto" :disabled="processing" @click="close">
                        {{ cancelLabel }}
                    </button>
                    <button type="button" :class="[confirmButtonClass(), 'btn-sm w-full sm:w-auto']" :disabled="processing" @click="confirmAction">
                        {{ processing ? 'Please wait…' : confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
