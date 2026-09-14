<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    approved: { type: String, required: true },
    released: { type: String, required: true },
    pending: { type: String, required: true },
    byProgram: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = useForm({
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

const submit = () => form.get(route('admin.reports.financial'), { preserveState: true });

const csvHref = computed(() => {
    const url = new URL(route('admin.reports.financial'), window.location.origin);
    if (form.date_from) url.searchParams.set('date_from', form.date_from);
    if (form.date_to) url.searchParams.set('date_to', form.date_to);
    url.searchParams.set('export', 'csv');
    return url.pathname + url.search;
});
</script>

<template>
    <AdminLayout>
        <Head title="Financial Report" />
        <PageHeader title="Financial Report" kicker="Assistance amounts">
            <template #actions>
                <button class="btn-ghost btn-sm no-print" type="button" @click="window.print()">Print / PDF</button>
                <a class="btn-secondary btn-sm no-print" :href="csvHref">Export CSV / Excel</a>
            </template>
        </PageHeader>
        <form class="panel mb-4 no-print" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-3">
                <div><label>Period from</label><input v-model="form.date_from" type="date"></div>
                <div><label>Period to</label><input v-model="form.date_to" type="date"></div>
                <div class="flex items-end"><button class="btn-primary" type="submit">Filter period</button></div>
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
                <tr v-for="row in byProgram" :key="row.name">
                    <td data-label="Program">{{ row.name }}</td>
                    <td data-label="Count">{{ row.count }}</td>
                    <td data-label="Amount">{{ row.total }}</td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
