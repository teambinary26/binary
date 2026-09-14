<script setup>
defineProps({
    class: { type: String, default: 'h-16 w-16' },
});
</script>

<template>
    <img
        :class="['bg-white object-contain', $props.class]"
        src="/images/logo.png"
        alt="Local Youth Development Office of Nabua"
        width="1254"
        height="1254"
    >
</template>
