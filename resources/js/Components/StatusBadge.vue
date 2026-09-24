<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: '' },
    tone: { type: String, default: 'neutral' },
    description: { type: String, default: '' },
});

const textClass = computed(() => ({
    success: 'text-gov-success',
    warning: 'text-[#8a6400]',
    danger: 'text-gov-danger',
    info: 'text-gov-blue',
}[props.tone] || 'text-gov-muted'));
</script>

<template>
    <span class="text-xs font-bold uppercase tracking-wide" :class="textClass" :title="description">{{ label || status }}</span>
</template>
