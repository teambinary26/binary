<script setup>
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    announcements: { type: Object, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head title="Announcements" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <h1>Announcements</h1>
            </div>
        </div>
        <div class="mx-auto max-w-7xl space-y-3 px-4 py-6 md:py-8">
            <article v-for="item in announcements.data" :key="item.id" class="panel">
                <div class="px-4 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gov-blue">{{ item.type_label }} · {{ item.published_at }}</p>
                    <h2 class="text-xl font-bold">
                        <Link :href="route('site.announcements.show', item.id)" class="text-gov-dark no-underline">{{ item.title }}</Link>
                    </h2>
                    <p class="mt-1 text-sm text-gov-muted">{{ item.excerpt }}</p>
                </div>
            </article>
            <p v-if="!announcements.data.length">No announcements have been published.</p>
            <Pagination :links="announcements.links" />
        </div>
    </PublicLayout>
</template>
