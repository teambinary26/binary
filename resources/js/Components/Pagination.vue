<script setup>
import { computed } from 'vue';

const props = defineProps({
    paginator: { type: Object, default: null },
    links: { type: Array, default: () => [] },
});

const resolvedLinks = computed(() => props.paginator?.links ?? props.links ?? []);
const from = computed(() => props.paginator?.from ?? null);
const to = computed(() => props.paginator?.to ?? null);
const total = computed(() => props.paginator?.total ?? null);
const showLinks = computed(() => resolvedLinks.value.length > 3);
const showSummary = computed(() => total.value !== null && total.value !== undefined);
const summary = computed(() => {
    if (! Number(total.value)) {
        return 'No records to display';
    }

    return `Showing ${from.value} to ${to.value} of ${total.value}`;
});
</script>

<template>
    <div
        v-if="showSummary || showLinks"
        class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
    >
        <p v-if="showSummary" class="text-sm text-gov-muted">{{ summary }}</p>
        <nav v-if="showLinks" class="flex max-w-full gap-1 overflow-x-auto pb-1" aria-label="Pagination">
            <component
                :is="link.url ? 'Link' : 'span'"
                v-for="(link, index) in resolvedLinks"
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
    </div>
</template>
