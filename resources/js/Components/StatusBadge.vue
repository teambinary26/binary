<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: '' },
    tone: { type: String, default: 'neutral' },
    description: { type: String, default: '' },
});

const badgeClass = computed(() => ({
    success: 'badge badge-success',
    warning: 'badge badge-warning',
    danger: 'badge badge-danger',
    info: 'badge badge-info',
}[props.tone] || 'badge badge-neutral'));
</script>

<template>
    <span :class="[badgeClass, 'max-w-full whitespace-normal break-words']" :title="description">{{ label || status }}</span>
</template>
