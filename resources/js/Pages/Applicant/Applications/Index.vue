<script setup>
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    applications: { type: Object, required: true },
});
</script>

<template>
    <ApplicantLayout>
        <Head title="My Applications" />
        <PageHeader title="My Applications" kicker="Filed requests" />
        <div v-if="applications.data.length">
            <table class="data-table">
                <thead><tr><th>Application no.</th><th>Program</th><th>Submitted</th><th>Status</th><th /></tr></thead>
                <tbody>
                    <tr v-for="application in applications.data" :key="application.id">
                        <td data-label="No.">{{ application.application_no }}</td>
                        <td data-label="Program">{{ application.program?.name }}</td>
                        <td data-label="Submitted">{{ application.submitted_at_full }}</td>
                        <td data-label="Status"><StatusBadge :label="application.status_label" :tone="application.status_tone" /></td>
                        <td data-label="Action"><Link :href="route('applicant.applications.show', application.id)">Track</Link></td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="applications.links" />
        </div>
        <p v-else class="panel px-4 py-6 text-sm text-gov-muted">
            No applications yet.
            <Link :href="route('applicant.programs.index')">View available programs</Link>.
        </p>
    </ApplicantLayout>
</template>
