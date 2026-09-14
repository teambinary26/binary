<script setup>
defineProps({
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex max-w-full gap-1 overflow-x-auto pb-1" aria-label="Pagination">
        <component
            :is="link.url ? 'Link' : 'span'"
            v-for="(link, index) in links"
            :key="index"
            :href="link.url || undefined"
            class="btn btn-sm shrink-0 whitespace-nowrap"
            :class="[
                link.active ? 'btn-primary' : 'btn-ghost',
                !link.url ? 'pointer-events-none opacity-50' : '',
            ]"
            v-html="link.label"
        />
    </nav>
</template>
