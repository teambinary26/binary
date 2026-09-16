<script setup>
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    title: { type: String, required: true },
    kicker: { type: String, default: '' },
    actionLabel: { type: String, default: 'Open' },
    applications: { type: Object, required: true },
});
</script>

<template>
    <AdminLayout>
        <Head :title="title" />
        <PageHeader :title="title" :kicker="kicker">
            <template #actions>
                <Link :href="route('admin.workflow.index')" class="btn-secondary btn-sm">Back to workflow</Link>
            </template>
        </PageHeader>
        <table class="data-table">
            <thead><tr><th>Application no.</th><th>Applicant</th><th>Program</th><th>Date</th><th>Status</th><th /></tr></thead>
            <tbody>
                <tr v-for="row in applications.data" :key="row.id">
                    <td data-label="No.">{{ row.application_no }}</td>
                    <td data-label="Applicant">{{ row.applicant?.full_name }}</td>
                    <td data-label="Program">{{ row.program?.name }}</td>
                    <td data-label="Date">{{ row.submitted_at }}</td>
                    <td data-label="Status"><StatusBadge :label="row.status_label" :tone="row.status_tone" /></td>
                    <td data-label="Action"><Link :href="route('admin.applications.show', row.id)">{{ actionLabel }}</Link></td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="applications" />
    </AdminLayout>
</template>
