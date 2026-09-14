<script setup>
defineProps({
    class: { type: String, default: 'h-14 w-14' },
});
</script>

<template>
    <img
        :class="['sidenav-logo shrink-0', $props.class]"
        src="/images/sidenav-logo.png"
        alt="Local Youth Development Office of Nabua"
        width="1024"
        height="1024"
    >
</template>
