<script setup>
defineProps({
    title: { type: String, required: true },
    kicker: { type: String, default: '' },
    documentNo: { type: String, default: '' },
    plain: { type: Boolean, default: false },
});
</script>

<template>
    <div class="mb-4 border-b pb-3 sm:mb-5" :class="plain ? 'border-gov-border' : 'border-b-2 border-gov-gold'">
        <p v-if="kicker" :class="plain ? 'text-[11px] font-bold uppercase tracking-[0.16em] text-gov-muted' : 'section-kicker'">{{ kicker }}</p>
        <div class="mt-1 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between sm:gap-3">
            <h1 class="page-title">{{ title }}</h1>
            <p v-if="documentNo" class="break-all text-xs font-semibold uppercase tracking-wide text-gov-muted">{{ documentNo }}</p>
        </div>
        <div v-if="$slots.actions" class="mt-3 flex flex-col flex-wrap gap-2 sm:flex-row">
            <slot name="actions" />
        </div>
    </div>
</template>
