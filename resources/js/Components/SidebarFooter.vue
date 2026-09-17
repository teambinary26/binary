<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    role: { type: String, default: '' },
    detail: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const initials = computed(() => {
    const parts = String(props.name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (! parts.length) {
        return '•';
    }

    return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
});
</script>

<template>
    <div
        class="shrink-0 border-t border-white/15 bg-[#001F45]"
        :class="compact ? 'px-2 py-3' : 'px-4 py-3'"
        :title="compact ? [name, role, detail].filter(Boolean).join(' — ') : undefined"
    >
        <div v-if="compact" class="flex justify-center">
            <span class="flex h-9 w-9 items-center justify-center bg-white/10 text-xs font-bold text-white">{{ initials }}</span>
        </div>
        <template v-else>
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-gov-gold">Signed in</p>
            <p class="mt-1 truncate text-sm font-semibold leading-tight text-white">{{ name || 'Account' }}</p>
            <p v-if="role" class="mt-0.5 truncate text-xs text-white/75">{{ role }}</p>
            <p v-if="detail" class="truncate text-[11px] text-white/60">{{ detail }}</p>
        </template>
    </div>
</template>
