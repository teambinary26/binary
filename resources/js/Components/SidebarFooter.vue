<script setup>
defineProps({
    name: { type: String, default: '' },
    role: { type: String, default: '' },
    detail: { type: String, default: '' },
});
</script>

<template>
    <div class="shrink-0 border-t border-white/15 bg-[#001F45] px-4 py-3">
        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-gov-gold">Signed in</p>
        <p class="mt-1 truncate text-sm font-semibold leading-tight text-white">{{ name || 'Account' }}</p>
        <p v-if="role" class="mt-0.5 truncate text-xs text-white/75">{{ role }}</p>
        <p v-if="detail" class="truncate text-[11px] text-white/60">{{ detail }}</p>
    </div>
</template>
