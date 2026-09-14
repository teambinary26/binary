<script setup>
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    applicant: { type: Object, required: true },
    applications: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout>
        <Head :title="applicant.full_name" />
        <PageHeader :title="applicant.full_name" kicker="Beneficiary record" :document-no="applicant.applicant_no" />
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="panel lg:col-span-1">
                <div class="panel-h">Profile</div>
                <dl class="divide-y divide-gov-border text-sm">
                    <div class="px-4 py-2">{{ applicant.full_address }}</div>
                    <div class="px-4 py-2">{{ applicant.contact_number }} · {{ applicant.email }}</div>
                    <div class="px-4 py-2">{{ applicant.date_of_birth }} · {{ applicant.sex }}</div>
                    <div class="px-4 py-2">{{ applicant.beneficiary_label }}</div>
                </dl>
            </div>
            <div class="panel lg:col-span-2">
                <div class="panel-h">Applications</div>
                <table class="data-table">
                    <thead><tr><th>No.</th><th>Program</th><th>Status</th><th /></tr></thead>
                    <tbody>
                        <tr v-for="row in applications" :key="row.id">
                            <td data-label="No.">{{ row.application_no }}</td>
                            <td data-label="Program">{{ row.program?.name }}</td>
                            <td data-label="Status"><StatusBadge :label="row.status_label" :tone="row.status_tone" /></td>
                            <td data-label="Action"><Link :href="route('admin.applications.show', row.id)">Open</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
