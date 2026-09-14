<script setup>
import { onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
    chartMonth: { type: Object, required: true },
    chartProgram: { type: Object, required: true },
    chartType: { type: Object, required: true },
    chartDecision: { type: Object, required: true },
    chartAmount: { type: Object, required: true },
});

const chartMonthEl = ref(null);
const chartProgramEl = ref(null);
const chartTypeEl = ref(null);
const chartDecisionEl = ref(null);
const chartAmountEl = ref(null);

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
};

onMounted(() => {
    const blue = '#002D62';
    const dark = '#C5A059';
    const warn = '#D99A00';
    const danger = '#C62828';
    const success = '#198754';

    const bar = (el, labels, data) => new Chart(el, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: blue, borderWidth: 0 }] },
        options: {
            ...chartDefaults,
            scales: {
                x: { ticks: { font: { size: 10 }, maxRotation: 0 } },
                y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
            },
        },
    });

    bar(chartMonthEl.value, props.chartMonth.labels, props.chartMonth.data);
    bar(chartProgramEl.value, props.chartProgram.labels, props.chartProgram.data);
    bar(chartAmountEl.value, props.chartAmount.labels, props.chartAmount.data);

    const donut = (el, labels, data, colors) => new Chart(el, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0 }] },
        options: {
            ...chartDefaults,
            cutout: '62%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 11 }, padding: 12 },
                },
            },
        },
    });

    donut(chartTypeEl.value, props.chartType.labels, props.chartType.data, [blue, dark, warn]);
    donut(chartDecisionEl.value, props.chartDecision.labels, props.chartDecision.data, [success, danger]);
});
</script>

<template>
    <AdminLayout>
        <Head title="Administration Dashboard" />
        <PageHeader title="Operations Dashboard" kicker="Municipal Social Welfare and Development Office" document-no="Official statistical overview" plain />
        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="stat-card"><div class="label">Total Applicants</div><div class="value text-2xl">{{ stats.applicants }}</div></div>
            <div class="stat-card"><div class="label">Total Applications</div><div class="value text-2xl">{{ stats.applications }}</div></div>
            <div class="stat-card"><div class="label">Pending Applications</div><div class="value text-2xl">{{ stats.pending }}</div></div>
            <div class="stat-card"><div class="label">Approved Applications</div><div class="value text-2xl">{{ stats.approved }}</div></div>
            <div class="stat-card"><div class="label">Assistance Released</div><div class="value text-2xl">{{ stats.released }}</div></div>
            <div class="stat-card"><div class="label">Total Amount Released</div><div class="value text-2xl">{{ stats.amount }}</div></div>
        </div>
        <div class="mb-5 grid gap-4 xl:grid-cols-2">
            <div class="panel">
                <div class="panel-h">Applications by month</div>
                <div class="p-4"><div class="chart-frame"><canvas ref="chartMonthEl" /></div></div>
            </div>
            <div class="panel">
                <div class="panel-h">Applications by program</div>
                <div class="p-4"><div class="chart-frame"><canvas ref="chartProgramEl" /></div></div>
            </div>
            <div class="panel">
                <div class="panel-h">Applicants by beneficiary type</div>
                <div class="p-4"><div class="chart-frame-donut"><canvas ref="chartTypeEl" /></div></div>
            </div>
            <div class="panel">
                <div class="panel-h">Approved vs rejected</div>
                <div class="p-4"><div class="chart-frame-donut"><canvas ref="chartDecisionEl" /></div></div>
            </div>
        </div>
        <div class="panel mb-5">
            <div class="panel-h">Assistance amount by program</div>
            <div class="p-4"><div class="chart-frame-wide"><canvas ref="chartAmountEl" /></div></div>
        </div>
        <div class="panel">
            <div class="panel-h">Recent applications</div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Application no.</th>
                            <th>Applicant</th>
                            <th>Program</th>
                            <th>Category</th>
                            <th>Date applied</th>
                            <th>Status</th>
                            <th>Assigned staff</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in recent" :key="row.id">
                            <td data-label="No.">{{ row.application_no }}</td>
                            <td data-label="Applicant">{{ row.applicant?.full_name }}</td>
                            <td data-label="Program">{{ row.program?.name }}</td>
                            <td data-label="Category">{{ row.program?.category_name }}</td>
                            <td data-label="Date">{{ row.submitted_at }}</td>
                            <td data-label="Status"><StatusBadge :label="row.status_label" :tone="row.status_tone" /></td>
                            <td data-label="Staff">{{ row.assigned_staff || '—' }}</td>
                            <td data-label="Action"><Link :href="route('admin.applications.show', row.id)">Open</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
