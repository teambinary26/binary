<script setup>
import { onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Toaster as Sonner } from 'vue-sonner';
import 'vue-sonner/style.css';
import { notifyFromFlash } from '@/composables/useNotify';

defineProps({
    position: { type: String, default: 'top-right' },
});

const page = usePage();

const flashFrom = (payload) => (
    payload?.detail?.page?.props?.flash
    ?? payload?.props?.flash
    ?? null
);

onMounted(() => {
    notifyFromFlash(page.props.flash);
});

const stop = router.on('success', (event) => {
    notifyFromFlash(flashFrom(event));
});

onUnmounted(() => {
    stop();
});
</script>

<template>
    <Teleport to="body">
        <Sonner
            close-button
            close-button-position="top-right"
            :position="position"
            :duration="4500"
            :toast-options="{
                class: 'cams-toast',
                closeButtonPosition: 'top-right',
            }"
        />
    </Teleport>
</template>
