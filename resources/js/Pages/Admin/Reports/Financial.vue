<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    approved: { type: String, required: true },
    released: { type: String, required: true },
    pending: { type: String, required: true },
    byProgram: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    generated_at: { type: String, default: '' },
});

const form = useForm({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const applyFilters = () => form.get(route('admin.reports.financial'), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

watch(() => [form.date_from, form.date_to], () => applyFilters());

const exportHref = (format) => {
    const url = new URL(route('admin.reports.financial'), window.location.origin);
    if (form.date_from) url.searchParams.set('date_from', form.date_from);
    if (form.date_to) url.searchParams.set('date_to', form.date_to);
    url.searchParams.set('export', format);
    return url.pathname + url.search;
};
</script>

<template>
    <AdminLayout>
        <Head title="Financial Report" />
        <PageHeader title="Financial Report" kicker="Assistance amounts">
            <template #actions>
                <a class="btn-secondary btn-sm" :href="exportHref('excel')">Export Excel</a>
                <a class="btn-secondary btn-sm" :href="exportHref('pdf')">Export PDF</a>
            </template>
        </PageHeader>
        <form class="panel mb-4" @submit.prevent="applyFilters">
            <div class="grid gap-3 p-4 md:grid-cols-2">
                <div><label>Period from</label><input v-model="form.date_from" type="date"></div>
                <div><label>Period to</label><input v-model="form.date_to" type="date"></div>
            </div>
        </form>
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <div class="stat-card"><div class="label">Total approved</div><div class="value text-2xl">{{ approved }}</div></div>
            <div class="stat-card"><div class="label">Total released</div><div class="value text-2xl">{{ released }}</div></div>
            <div class="stat-card"><div class="label">Total pending release</div><div class="value text-2xl">{{ pending }}</div></div>
        </div>
        <table class="data-table">
            <thead><tr><th>Program</th><th>No. of releases</th><th>Amount by program</th></tr></thead>
            <tbody>
                <tr v-if="!byProgram.data.length">
                    <td class="px-4 py-6 text-sm text-gov-muted" colspan="3">No releases match the current period.</td>
                </tr>
                <tr v-for="row in byProgram.data" :key="row.name">
                    <td data-label="Program">{{ row.name }}</td>
                    <td data-label="Count">{{ row.count }}</td>
                    <td data-label="Amount">{{ row.total }}</td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="byProgram" />
    </AdminLayout>
</template>
