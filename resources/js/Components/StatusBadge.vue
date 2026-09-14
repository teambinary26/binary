<script setup>
defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: '' },
    tone: { type: String, default: 'neutral' },
    description: { type: String, default: '' },
});
</script>

<template>
    <span class="badge" :class="`badge-${tone}`" :title="description">{{ label || status }}</span>
</template>
