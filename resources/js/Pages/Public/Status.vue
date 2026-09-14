<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    application: { type: Object, default: null },
    searched: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
});

const form = useForm({
    application_no: props.filters.application_no || '',
});

const submit = () => form.get(route('site.status', undefined, false));
</script>

<template>
    <PublicLayout>
        <Head title="Check Application Status" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <h1>Check Application Status</h1>
                <p class="mt-2 text-sm text-[#d7e6f7] md:text-base">Enter the official application number to view the current status.</p>
            </div>
        </div>
        <div class="mx-auto max-w-3xl px-4 py-6 md:py-8">
            <form class="panel mb-6" @submit.prevent="submit">
                <div class="panel-h">Public status inquiry</div>
                <div class="p-4">
                    <label for="application_no">Application number</label>
                    <input id="application_no" v-model="form.application_no" placeholder="CAMS-2026-000001" required>
                </div>
                <div class="px-4 pb-4"><button class="btn-primary w-full sm:w-auto" type="submit">Check status</button></div>
            </form>

            <div v-if="searched && !application" class="flash flash-warning">No matching application was found. Verify the application number.</div>

            <div v-if="application" class="panel mb-4">
                <div class="panel-h">{{ application.application_no }}</div>
                <div class="grid gap-3 p-4 text-sm sm:grid-cols-2">
                    <p><span class="text-xs font-bold uppercase text-gov-muted">Applicant</span><br>{{ application.applicant?.full_name }}</p>
                    <p><span class="text-xs font-bold uppercase text-gov-muted">Program</span><br>{{ application.program?.name }}</p>
                    <p><span class="text-xs font-bold uppercase text-gov-muted">Date applied</span><br>{{ application.submitted_at_full }}</p>
                    <p>
                        <span class="text-xs font-bold uppercase text-gov-muted">Status</span><br>
                        <StatusBadge :status="application.status" :label="application.status_label" :tone="application.status_tone" />
                    </p>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
