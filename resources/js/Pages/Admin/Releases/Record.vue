<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    query: { type: String, default: '' },
    matches: { type: Array, default: () => [] },
    searched: { type: Boolean, default: false },
});

const search = useForm({
    q: props.query || '',
});

const amounts = reactive({});
const notes = reactive({});

const record = useForm({
    application_id: '',
    amount: '',
    remarks: '',
});

const visibleMatches = computed(() => (search.q.trim() === '' ? [] : props.matches));

watch(() => props.matches, (rows) => {
    rows.forEach((application) => {
        if (amounts[application.id] === undefined) {
            amounts[application.id] = application.approved_amount ?? '';
        }
    });
}, { immediate: true });

const finding = ref(false);
let searchTimer = null;

const findApplicant = () => {
    const term = search.q.trim();

    search.get(route('admin.releases.record.create'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            if (search.q.trim() === term) {
                finding.value = false;
            }
        },
    });
};

watch(() => search.q, (value) => {
    clearTimeout(searchTimer);

    if (value.trim() === '') {
        finding.value = false;
        findApplicant();
        return;
    }

    finding.value = true;
    searchTimer = setTimeout(findApplicant, 300);
});

const saveRecord = (application) => {
    record.application_id = application.id;
    record.amount = amounts[application.id] || application.approved_amount || '';
    record.remarks = notes[application.id] || '';
    record.post(route('admin.releases.record'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Record actual release" />
        <PageHeader title="Record actual release" kicker="Search a scheduled applicant">
            <template #actions>
                <Link class="btn-secondary btn-sm" :href="route('admin.releases.index')">Back to schedule</Link>
            </template>
        </PageHeader>

        <form class="panel mb-4" @submit.prevent="findApplicant">
            <div class="p-4">
                <label for="release-search">Search</label>
                <input id="release-search" v-model="search.q" placeholder="Applicant name or application no." autofocus>
            </div>
        </form>

        <div v-if="finding" class="flex flex-col items-center justify-center gap-3 py-24 text-sm font-semibold text-gov-blue">
            <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-gov-border border-t-gov-blue" aria-hidden="true" />
            Finding
        </div>

        <p v-else-if="search.q.trim() && searched && search.q.trim() === query && !visibleMatches.length" class="panel px-4 py-6 text-sm text-gov-muted">
            No scheduled applicant matches that search.
        </p>

        <article v-for="application in visibleMatches" v-else :key="application.id" class="panel mb-4">
            <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                <span>{{ application.applicant?.full_name }}</span>
                <StatusBadge :label="application.status_label" :tone="application.status_tone" />
            </div>
            <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div>
                    <p class="text-sm font-semibold text-gov-dark">{{ application.program?.name }}</p>
                    <p class="text-xs text-gov-muted">{{ application.program?.category_name }} · {{ application.application_no }}</p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div v-for="detail in application.details" :key="detail.field_name">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">{{ detail.field_label }}</dt>
                            <dd class="mt-1">{{ detail.value }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Approved amount</dt>
                            <dd class="mt-1">{{ application.approved_amount_formatted }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Release date</dt>
                            <dd class="mt-1">{{ application.schedule?.release_date || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Location</dt>
                            <dd class="mt-1">{{ application.schedule?.release_location || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Method</dt>
                            <dd class="mt-1">{{ application.schedule?.method_label || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Schedule notes</dt>
                            <dd class="mt-1">{{ application.schedule?.notes || '—' }}</dd>
                        </div>
                    </dl>
                </div>
                <form class="space-y-3 border border-gov-border p-3" @submit.prevent="saveRecord(application)">
                    <div>
                        <label>Amount</label>
                        <input v-model="amounts[application.id]" type="number" step="0.01" required>
                        <p v-if="record.errors.amount" class="field-error">{{ record.errors.amount }}</p>
                    </div>
                    <div>
                        <label>Remarks</label>
                        <textarea v-model="notes[application.id]" rows="3" />
                    </div>
                    <p v-if="record.errors.application_id || record.errors.application" class="field-error">
                        {{ record.errors.application_id || record.errors.application }}
                    </p>
                    <button class="btn-success w-full cursor-pointer" type="submit" :disabled="record.processing">Record release</button>
                </form>
            </div>
        </article>
    </AdminLayout>
</template>
