<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    announcement: { type: Object, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head :title="announcement.title" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">{{ announcement.type_label }}</p>
                <h1 class="mt-1">{{ announcement.title }}</h1>
                <p class="mt-2 text-sm text-[#d7e6f7]">Published {{ announcement.published_at }}</p>
            </div>
        </div>
        <div class="mx-auto max-w-3xl px-4 py-8">
            <article class="panel panel-body whitespace-pre-line text-sm leading-relaxed">{{ announcement.body }}</article>
        </div>
    </PublicLayout>
</template>
