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
    releases: { type: Object, required: true },
    programs: { type: Array, default: () => [] },
    barangays: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', program: '', date_from: '', date_to: '', barangay: '' }) },
    generated_at: { type: String, default: '' },
});

const filter = useForm({
    q: props.filters.q || '',
    program: props.filters.program || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    barangay: props.filters.barangay || '',
});

const applyFilters = () => filter.get(route('admin.releases.released'), { preserveState: true, preserveScroll: true });

const clearFilters = () => {
    filter.q = '';
    filter.program = '';
    filter.date_from = '';
    filter.date_to = '';
    filter.barangay = '';
    applyFilters();
};

const exportHref = (format) => {
    const url = new URL(route('admin.releases.released'), window.location.origin);
    Object.entries(filter.data()).forEach(([key, value]) => {
        if (value) {
            url.searchParams.set(key, value);
        }
    });
    url.searchParams.set('export', format);
    return url.pathname + url.search;
};
</script>

<template>
    <AdminLayout>
        <Head title="Released Assistance" />
        <PageHeader title="Released Assistance" kicker="Disbursement register">
            <template #actions>
                <a class="btn-secondary btn-sm" :href="exportHref('excel')">Export Excel</a>
                <a class="btn-secondary btn-sm" :href="exportHref('pdf')">Export PDF</a>
            </template>
        </PageHeader>

        <form class="panel mb-4" @submit.prevent="applyFilters">
            <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label>Search</label>
                    <input v-model="filter.q" placeholder="Name, application, reference, or code">
                </div>
                <div>
                    <label>Program</label>
                    <select v-model="filter.program">
                        <option value="">All programs</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Released from</label>
                    <input v-model="filter.date_from" type="date">
                </div>
                <div>
                    <label>Released to</label>
                    <input v-model="filter.date_to" type="date">
                </div>
                <div>
                    <label>Barangay</label>
                    <select v-model="filter.barangay">
                        <option value="">All barangays</option>
                        <option v-for="barangay in barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="btn-primary w-full" type="submit">Filter</button>
                    <button class="btn-ghost w-full" type="button" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <p class="mb-2 text-xs text-gov-muted">{{ gov?.agency }} · Generated {{ generated_at }} · {{ releases.total }} record(s)</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference no.</th>
                    <th>Application</th>
                    <th>Applicant</th>
                    <th>Program</th>
                    <th>Amount</th>
                    <th>Released</th>
                    <th>Officer</th>
                    <th>Verification code</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!releases.data.length">
                    <td class="px-4 py-6 text-sm text-gov-muted" colspan="8">No released assistance matches the current filter.</td>
                </tr>
                <tr v-for="row in releases.data" :key="row.id">
                    <td data-label="Ref">{{ row.reference_no }}</td>
                    <td data-label="App">{{ row.application?.application_no }}</td>
                    <td data-label="Applicant">{{ row.application?.applicant?.full_name }}</td>
                    <td data-label="Program">{{ row.application?.program?.name }}</td>
                    <td data-label="Amount">{{ row.amount_formatted }}</td>
                    <td data-label="Date">{{ row.released_at }}</td>
                    <td data-label="Officer">{{ row.officer }}</td>
                    <td data-label="Code">{{ row.verification_code }}</td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="releases" />
    </AdminLayout>
</template>
