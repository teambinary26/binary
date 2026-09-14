<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <PublicLayout>
        <div class="mx-auto w-full px-4 py-6 md:py-10" :class="wide ? 'max-w-3xl' : 'max-w-lg'">
            <slot />
        </div>
    </PublicLayout>
</template>
