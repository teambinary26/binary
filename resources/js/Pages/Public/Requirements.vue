<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    programs: { type: Object, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head title="Documentary Requirements" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <h1>Documentary Requirements</h1>
                <p class="mt-2 text-sm text-[#d7e6f7] md:text-base">Requirements are defined per program by administrators and are shown automatically on the application form.</p>
            </div>
        </div>
        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 md:py-8">
            <div v-for="program in programs.data" :key="program.id" class="panel">
                <div class="panel-h">{{ program.name }} <span class="font-normal normal-case tracking-normal">({{ program.code }})</span></div>
                <ul class="divide-y divide-gov-border text-sm">
                    <li v-for="requirement in program.requirements" :key="requirement.id" class="px-4 py-2">
                        <strong>{{ requirement.name }}</strong>
                        <template v-if="requirement.description"> — {{ requirement.description }}</template>
                    </li>
                </ul>
            </div>
            <p v-if="!programs.data.length" class="panel px-4 py-6 text-sm text-gov-muted">No program requirements have been published.</p>
            <Pagination :paginator="programs" />
        </div>
    </PublicLayout>
</template>
