<script setup>
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    byBarangay: { type: Array, default: () => [] },
    byType: { type: Array, default: () => [] },
    bySex: { type: Array, default: () => [] },
});

const csvHref = () => {
    const url = new URL(route('admin.reports.beneficiaries'), window.location.origin);
    url.searchParams.set('export', 'csv');
    return url.pathname + url.search;
};
</script>

<template>
    <AdminLayout>
        <Head title="Demographic Report" />
        <PageHeader title="Beneficiary / Demographic Report" kicker="Applicant statistics">
            <template #actions>
                <button class="btn-ghost btn-sm no-print" type="button" @click="window.print()">Print / PDF</button>
                <a class="btn-secondary btn-sm no-print" :href="csvHref()">Export CSV / Excel</a>
            </template>
        </PageHeader>
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-h">By barangay</div>
                <table class="data-table">
                    <thead><tr><th>Barangay</th><th>Applicants</th></tr></thead>
                    <tbody>
                        <tr v-for="row in byBarangay" :key="row.barangay">
                            <td data-label="Barangay">{{ row.barangay }}</td>
                            <td data-label="Total">{{ row.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="space-y-4">
                <div class="panel">
                    <div class="panel-h">By beneficiary type</div>
                    <table class="data-table">
                        <thead><tr><th>Type</th><th>Total</th></tr></thead>
                        <tbody>
                            <tr v-for="row in byType" :key="row.type">
                                <td data-label="Type">{{ row.type }}</td>
                                <td data-label="Total">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="panel">
                    <div class="panel-h">By sex</div>
                    <table class="data-table">
                        <thead><tr><th>Sex</th><th>Total</th></tr></thead>
                        <tbody>
                            <tr v-for="row in bySex" :key="row.sex">
                                <td data-label="Sex">{{ row.sex }}</td>
                                <td data-label="Total">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
