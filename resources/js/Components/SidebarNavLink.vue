<script setup>
defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: Array, required: true },
    active: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        class="sidebar-link"
        :class="{ active, 'sidebar-link-compact': compact }"
        :title="label"
        :aria-label="label"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path v-for="(d, index) in icon" :key="index" :d="d" />
        </svg>
        <span :class="compact ? 'sr-only' : 'truncate'">{{ label }}</span>
    </Link>
</template>
