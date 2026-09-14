<script setup>
import { computed } from 'vue';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    programs: { type: Array, default: () => [] },
    currentApplication: { type: Object, default: null },
});

const hasCurrent = computed(() => Boolean(props.currentApplication));
const isCurrent = (program) => props.currentApplication?.program_id === program.id;
const currentHref = computed(() => {
    if (! props.currentApplication) {
        return null;
    }

    return route('applicant.applications.show', props.currentApplication.id);
});
</script>

<template>
    <ApplicantLayout>
        <Head title="Available Programs" />
        <PageHeader title="Available Programs" kicker="Select a program to begin" />
        <p v-if="hasCurrent" class="mb-4 break-words text-sm text-gov-muted">
            You currently have an application for <strong>{{ currentApplication.program_name }}</strong>.
            Other programs are disabled until that application is completed, rejected, or cancelled.
        </p>
        <div class="grid gap-4">
            <article v-for="program in programs" :key="program.id" class="panel overflow-hidden" :class="{ 'opacity-60': hasCurrent && ! isCurrent(program) }">
                <div class="panel-h flex flex-col items-start gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <span class="min-w-0 break-words">{{ program.name }}</span>
                    <span class="shrink-0 text-xs font-normal normal-case">{{ program.amount_display }}</span>
                </div>
                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                    <div class="min-w-0 max-w-3xl text-sm">
                        <p class="break-words">{{ program.description }}</p>
                        <p class="mt-2 text-gov-muted">{{ program.category?.name }} · {{ program.availability_label }}</p>
                    </div>
                    <Link
                        v-if="isCurrent(program)"
                        class="btn-primary w-full text-center sm:w-auto"
                        :href="currentHref"
                    >Your current program</Link>
                    <button
                        v-else-if="hasCurrent"
                        class="btn-primary w-full cursor-not-allowed opacity-50 sm:w-auto"
                        type="button"
                        disabled
                    >Apply</button>
                    <Link
                        v-else-if="program.is_currently_open"
                        class="btn-primary w-full text-center sm:w-auto"
                        :href="route('site.apply.create', program.slug)"
                    >Apply</Link>
                    <span v-else class="badge badge-neutral">Closed</span>
                </div>
            </article>
        </div>
    </ApplicantLayout>
</template>
