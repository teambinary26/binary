<script setup>
import { onUnmounted, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    wide: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const close = () => emit('close');

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

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
                class="relative z-10 flex max-h-[100dvh] w-full flex-col overflow-y-auto border border-gov-border bg-white shadow-xl sm:max-h-[92vh]"
                :class="wide ? 'max-w-2xl' : 'max-w-lg'"
                role="dialog"
                aria-modal="true"
                aria-labelledby="form-modal-title"
            >
                <div class="flex items-center justify-between border-b border-gov-border px-4 py-3">
                    <h3 id="form-modal-title" class="text-sm font-bold uppercase tracking-wide text-gov-dark">
                        {{ title }}
                    </h3>
                    <button class="btn-ghost btn-sm" type="button" @click="close">Close</button>
                </div>
                <slot />
            </div>
        </div>
    </Teleport>
</template>
