<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

const page = usePage();
const gov = computed(() => page.props.gov);

const props = defineProps({
    applications: { type: Object, required: true },
    programs: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    barangays: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    generated_at: { type: String, default: '' },
});

const form = useForm({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    program: props.filters.program || '',
    status: props.filters.status || '',
    barangay: props.filters.barangay || '',
    beneficiary: props.filters.beneficiary || '',
});

const submit = () => form.get(route('admin.reports.applications'), { preserveState: true });

const csvHref = computed(() => {
    const url = new URL(route('admin.reports.applications'), window.location.origin);
    Object.entries(form.data()).forEach(([key, value]) => {
        if (value) {
            url.searchParams.set(key, value);
        }
    });
    url.searchParams.set('export', 'csv');
    return url.pathname + url.search;
});
</script>

<template>
    <AdminLayout>
        <Head title="Application Report" />
        <PageHeader title="Application Report" kicker="Official statistical listing">
            <template #actions>
                <button class="btn-ghost btn-sm no-print" type="button" @click="window.print()">Print</button>
                <a class="btn-secondary btn-sm no-print" :href="csvHref">Export CSV / Excel</a>
                <button class="btn-secondary btn-sm no-print" type="button" @click="window.print()">Export PDF</button>
            </template>
        </PageHeader>
        <form class="panel mb-4 no-print" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-3 lg:grid-cols-6">
                <div><label>Date from</label><input v-model="form.date_from" type="date"></div>
                <div><label>Date to</label><input v-model="form.date_to" type="date"></div>
                <div>
                    <label>Program</label>
                    <select v-model="form.program">
                        <option value="">All</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select v-model="form.status">
                        <option value="">All</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div>
                    <label>Barangay</label>
                    <select v-model="form.barangay">
                        <option value="">All</option>
                        <option v-for="barangay in barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                    </select>
                </div>
                <div>
                    <label>Beneficiary type</label>
                    <select v-model="form.beneficiary">
                        <option value="">All</option>
                        <option value="student">Student</option>
                        <option value="non_student">Non-student</option>
                    </select>
                </div>
            </div>
            <div class="px-4 pb-4"><button class="btn-primary" type="submit">Apply filters</button></div>
        </form>
        <p class="mb-2 text-xs text-gov-muted">{{ gov.agency }} · Generated {{ generated_at }} · {{ applications.total }} record(s)</p>
        <table class="data-table">
            <thead><tr><th>Application no.</th><th>Applicant</th><th>Program</th><th>Barangay</th><th>Status</th><th>Date applied</th><th>Amount</th></tr></thead>
            <tbody>
                <tr v-for="row in applications.data" :key="row.id">
                    <td data-label="No.">{{ row.application_no }}</td>
                    <td data-label="Applicant">{{ row.applicant?.full_name }}</td>
                    <td data-label="Program">{{ row.program?.name }}</td>
                    <td data-label="Barangay">{{ row.applicant?.barangay }}</td>
                    <td data-label="Status">{{ row.status_label }}</td>
                    <td data-label="Date">{{ row.submitted_at }}</td>
                    <td data-label="Amount">{{ row.approved_amount_formatted }}</td>
                </tr>
            </tbody>
        </table>
        <div class="no-print"><Pagination :links="applications.links" /></div>
    </AdminLayout>
</template>
