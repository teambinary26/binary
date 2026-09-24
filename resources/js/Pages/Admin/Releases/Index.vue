<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    schedules: { type: Object, required: true },
    approved: { type: Object, required: true },
    forRelease: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', program: '', release_date: '', schedule_status: '' }) },
    methods: { type: Object, default: () => ({}) },
});

const methodEntries = computed(() => Object.entries(props.methods));
const showSchedule = ref(false);
const showReschedule = ref(false);
const selectedIds = ref([]);
const selectedScheduleIds = ref([]);
const rescheduleTargets = ref([]);

const filter = useForm({
    q: props.filters.q || '',
    program: props.filters.program || '',
    release_date: props.filters.release_date || '',
    schedule_status: props.filters.schedule_status || '',
});

const schedule = useForm({
    application_id: '',
    application_ids: [],
    release_date: '',
    release_location: 'MSWDO Window 2, Nabua Local Government Center',
    release_method: 'cash',
    notes: '',
});

const reschedule = useForm({
    release_date: '',
    release_location: 'MSWDO Window 2, Nabua Local Government Center',
    release_method: 'cash',
    notes: '',
});

const canRecordSchedule = (row) => row.status === 'scheduled' && row.application?.status === 'scheduled_for_release';

const approvedRows = computed(() => props.approved.data ?? []);
const approvedIds = computed(() => approvedRows.value.map((row) => row.id));
const selectedApproved = computed(() => approvedRows.value.filter((row) => selectedIds.value.includes(row.id)));
const allSelected = computed(() => approvedIds.value.length > 0 && approvedIds.value.every((id) => selectedIds.value.includes(id)));
const someSelected = computed(() => selectedApproved.value.length > 0 && ! allSelected.value);
const selectedScheduleApps = computed(() => {
    const ids = schedule.application_ids.map((id) => Number(id));

    return approvedRows.value.filter((row) => ids.includes(Number(row.id)));
});
const reschedulableSchedules = computed(() => (props.schedules.data ?? []).filter((row) => canRecordSchedule(row)));
const reschedulableIds = computed(() => reschedulableSchedules.value.map((row) => row.id));
const selectedSchedules = computed(() => (props.schedules.data ?? []).filter((row) => selectedScheduleIds.value.includes(row.id) && canRecordSchedule(row)));
const allSchedulesSelected = computed(() => reschedulableIds.value.length > 0 && reschedulableIds.value.every((id) => selectedScheduleIds.value.includes(id)));
const someSchedulesSelected = computed(() => selectedSchedules.value.length > 0 && ! allSchedulesSelected.value);

watch(approvedIds, (ids) => {
    selectedIds.value = selectedIds.value.filter((id) => ids.includes(id));
});

const isSelected = (id) => selectedIds.value.includes(id);
const isScheduleSelected = (id) => selectedScheduleIds.value.includes(id);

const toggleRow = (id) => {
    if (isSelected(id)) {
        selectedIds.value = selectedIds.value.filter((value) => value !== id);
        return;
    }

    selectedIds.value = [...selectedIds.value, id];
};

const toggleAll = () => {
    selectedIds.value = allSelected.value ? [] : [...approvedIds.value];
};

const toggleScheduleRow = (id) => {
    if (isScheduleSelected(id)) {
        selectedScheduleIds.value = selectedScheduleIds.value.filter((value) => value !== id);
        return;
    }

    selectedScheduleIds.value = [...selectedScheduleIds.value, id];
};

const toggleAllSchedules = () => {
    selectedScheduleIds.value = allSchedulesSelected.value ? [] : [...reschedulableIds.value];
};

let searchTimer = null;

const applyFilters = () => filter.get(route('admin.releases.index'), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

watch(
    () => [filter.q, filter.program, filter.release_date, filter.schedule_status],
    (values, previous) => {
        clearTimeout(searchTimer);
        const searchChanged = values[0] !== previous?.[0];
        if (searchChanged && values[0]) {
            searchTimer = setTimeout(applyFilters, 300);
            return;
        }
        applyFilters();
    },
);

const clearFilters = () => {
    filter.q = '';
    filter.program = '';
    filter.release_date = '';
    filter.schedule_status = '';
};

const openSchedule = (row = null) => {
    const ids = row
        ? [row.id]
        : selectedApproved.value.map((item) => item.id);

    schedule.application_id = ids.length === 1 ? ids[0] : '';
    schedule.application_ids = ids;
    schedule.release_date = '';
    schedule.notes = '';
    schedule.clearErrors();
    showSchedule.value = true;
};

const closeSchedule = () => {
    if (! schedule.processing) {
        showSchedule.value = false;
    }
};

const openReschedule = (row = null) => {
    const rows = row ? [row] : selectedSchedules.value;

    if (! rows.length) {
        return;
    }

    rescheduleTargets.value = rows;
    reschedule.release_date = rows[0].release_date_input || '';
    reschedule.release_location = rows[0].release_location || 'MSWDO Window 2, Nabua Local Government Center';
    reschedule.release_method = rows[0].release_method || 'cash';
    reschedule.notes = rows[0].notes || '';
    reschedule.clearErrors();
    showReschedule.value = true;
};

const closeReschedule = () => {
    if (! reschedule.processing) {
        showReschedule.value = false;
        rescheduleTargets.value = [];
    }
};

const saveReschedule = () => {
    const ids = rescheduleTargets.value.map((row) => row.id);

    if (! ids.length) {
        return;
    }

    reschedule.transform((data) => ({
        schedule_ids: ids,
        release_date: data.release_date,
        release_location: data.release_location,
        release_method: data.release_method,
        notes: data.notes,
    })).post(route('admin.releases.reschedule-many'), {
        preserveScroll: true,
        onSuccess: () => {
            showReschedule.value = false;
            rescheduleTargets.value = [];
            selectedScheduleIds.value = [];
        },
    });
};

const saveSchedule = () => {
    const ids = schedule.application_ids.length
        ? schedule.application_ids
        : (schedule.application_id ? [schedule.application_id] : []);

    schedule.transform((data) => ({
        application_ids: ids,
        release_date: data.release_date,
        release_location: data.release_location,
        release_method: data.release_method,
        notes: data.notes,
    })).post(route('admin.releases.schedule'), {
        preserveScroll: true,
        onSuccess: () => {
            showSchedule.value = false;
            selectedIds.value = [];
        },
    });
};

</script>

<template>
    <AdminLayout>
        <Head title="Release Schedule" />
        <PageHeader title="Release Schedule" kicker="Approved assistance for disbursement">
            <template #actions>
                <button class="btn-primary btn-sm" type="button" :disabled="!approvedRows.length && !selectedApproved.length" @click="openSchedule()">
                    {{ selectedApproved.length ? `Schedule selected (${selectedApproved.length})` : 'Schedule a release' }}
                </button>
                <Link class="btn-success btn-sm cursor-pointer" :href="route('admin.releases.record.create')">
                    Record actual release
                </Link>
            </template>
        </PageHeader>

        <form class="panel mb-4" @submit.prevent="applyFilters">
            <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label>Search</label>
                    <input v-model="filter.q" placeholder="Name or application no.">
                </div>
                <div>
                    <label>Program</label>
                    <select v-model="filter.program">
                        <option value="">All programs</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                </div>
                <div>
                    <label>Schedule date</label>
                    <input v-model="filter.release_date" type="date">
                </div>
                <div>
                    <label>Schedule status</label>
                    <select v-model="filter.schedule_status">
                        <option value="">All schedules</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="flex items-end md:col-span-2 xl:col-span-4">
                    <button class="btn-ghost" type="button" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <div class="panel">
            <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                <span>Approved applicants</span>
                <button
                    class="btn-primary btn-sm"
                    type="button"
                    :disabled="!selectedApproved.length"
                    @click="openSchedule()"
                >
                    Schedule selected ({{ selectedApproved.length }})
                </button>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    :disabled="!approvedRows.length"
                                    @change="toggleAll"
                                >
                                <span class="sr-only">Select all</span>
                            </label>
                        </th>
                        <th>Application no.</th>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Approved amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!approvedRows.length">
                        <td class="px-4 py-6 text-sm text-gov-muted" colspan="7">No approved applicants match the current filter.</td>
                    </tr>
                    <tr v-for="row in approvedRows" :key="row.id">
                        <td data-label="Select">
                            <input type="checkbox" :checked="isSelected(row.id)" @change="toggleRow(row.id)">
                        </td>
                        <td data-label="No.">
                            <Link class="font-semibold" :href="route('admin.applications.show', row.id)">{{ row.application_no }}</Link>
                        </td>
                        <td data-label="Applicant">{{ row.applicant?.full_name }}</td>
                        <td data-label="Program">{{ row.program?.name }}</td>
                        <td data-label="Amount">{{ row.approved_amount_formatted }}</td>
                        <td data-label="Status">
                            <StatusBadge :label="row.status_label" :tone="row.status_tone" :description="row.status_description" />
                        </td>
                        <td data-label="Action">
                            <button class="btn-primary btn-sm" type="button" @click="openSchedule(row)">Schedule</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="px-4 pb-4"><Pagination :paginator="approved" /></div>
        </div>

        <div class="panel mt-4">
            <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                <span>Scheduled releases</span>
                <button
                    class="btn-secondary btn-sm"
                    type="button"
                    :disabled="!selectedSchedules.length"
                    @click="openReschedule()"
                >
                    Reschedule selected ({{ selectedSchedules.length }})
                </button>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :checked="allSchedulesSelected"
                                    :indeterminate="someSchedulesSelected"
                                    :disabled="!reschedulableIds.length"
                                    @change="toggleAllSchedules"
                                >
                                <span class="sr-only">Select all scheduled</span>
                            </label>
                        </th>
                        <th>Application no.</th>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Approved amount</th>
                        <th>Release date</th>
                        <th>Location</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!schedules.data.length">
                        <td class="px-4 py-6 text-sm text-gov-muted" colspan="10">No scheduled releases match the current filter.</td>
                    </tr>
                    <tr v-for="row in schedules.data" :key="row.id">
                        <td data-label="Select">
                            <input
                                type="checkbox"
                                :checked="isScheduleSelected(row.id)"
                                :disabled="!canRecordSchedule(row)"
                                @change="toggleScheduleRow(row.id)"
                            >
                        </td>
                        <td data-label="No.">{{ row.application?.application_no }}</td>
                        <td data-label="Applicant">{{ row.application?.applicant?.full_name }}</td>
                        <td data-label="Program">{{ row.application?.program?.name }}</td>
                        <td data-label="Amount">{{ row.application?.approved_amount_formatted }}</td>
                        <td data-label="Date">{{ row.release_date }}</td>
                        <td data-label="Location">{{ row.release_location }}</td>
                        <td data-label="Method">{{ row.method_label }}</td>
                        <td data-label="Status">{{ row.status }}</td>
                        <td data-label="Action">
                            <div class="flex flex-wrap justify-end gap-2 sm:justify-start">
                                <button
                                    v-if="canRecordSchedule(row)"
                                    class="btn-secondary btn-sm"
                                    type="button"
                                    @click="openReschedule(row)"
                                >
                                    Reschedule
                                </button>
                                <Link
                                    v-if="canRecordSchedule(row)"
                                    class="btn-success btn-sm cursor-pointer"
                                    :href="route('admin.releases.record.create', { q: row.application?.application_no })"
                                >
                                    Record
                                </Link>
                                <span v-if="!canRecordSchedule(row)" class="text-xs text-gov-muted">—</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="p-4"><Pagination :paginator="schedules" /></div>
        </div>

        <Modal :show="showSchedule" title="Schedule a release" @close="closeSchedule">
            <form class="grid gap-3 p-4" @submit.prevent="saveSchedule">
                <div v-if="selectedScheduleApps.length">
                    <label>Selected applicants ({{ selectedScheduleApps.length }})</label>
                    <ul class="mt-1 max-h-40 space-y-1 overflow-y-auto border border-gov-border bg-gov-off px-3 py-2 text-sm">
                        <li v-for="row in selectedScheduleApps" :key="row.id">
                            {{ row.application_no }} — {{ row.applicant?.full_name }}
                        </li>
                    </ul>
                </div>
                <div v-else>
                    <label>Approved application</label>
                    <select v-model="schedule.application_id" required>
                        <option value="">Select</option>
                        <option v-for="row in approvedRows" :key="row.id" :value="row.id">
                            {{ row.application_no }} — {{ row.applicant?.full_name }} ({{ row.approved_amount_formatted }})
                        </option>
                    </select>
                </div>
                <p v-if="schedule.errors.application_id || schedule.errors.application_ids || schedule.errors.application" class="field-error">
                    {{ schedule.errors.application_id || schedule.errors.application_ids || schedule.errors.application }}
                </p>
                <div>
                    <label>Release date</label>
                    <input v-model="schedule.release_date" type="date" required>
                    <p v-if="schedule.errors.release_date" class="field-error">{{ schedule.errors.release_date }}</p>
                </div>
                <div>
                    <label>Release location</label>
                    <input v-model="schedule.release_location" required>
                    <p v-if="schedule.errors.release_location" class="field-error">{{ schedule.errors.release_location }}</p>
                </div>
                <div>
                    <label>Release method</label>
                    <select v-model="schedule.release_method">
                        <option v-for="[value, label] in methodEntries" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label>Notes</label>
                    <textarea v-model="schedule.notes" rows="2" />
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button class="btn-ghost" type="button" :disabled="schedule.processing" @click="closeSchedule">Cancel</button>
                    <button class="btn-primary" type="submit" :disabled="schedule.processing">
                        {{ selectedScheduleApps.length > 1 ? `Schedule ${selectedScheduleApps.length} applicants` : 'Schedule' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="showReschedule" title="Reschedule release" @close="closeReschedule">
            <form class="grid gap-3 p-4" @submit.prevent="saveReschedule">
                <div v-if="rescheduleTargets.length">
                    <label>Selected applicants ({{ rescheduleTargets.length }})</label>
                    <ul class="mt-1 max-h-40 space-y-1 overflow-y-auto border border-gov-border bg-gov-off px-3 py-2 text-sm">
                        <li v-for="row in rescheduleTargets" :key="row.id">
                            {{ row.application?.application_no }} — {{ row.application?.applicant?.full_name }}
                        </li>
                    </ul>
                </div>
                <p v-if="reschedule.errors.schedule || reschedule.errors.schedule_ids" class="field-error">
                    {{ reschedule.errors.schedule || reschedule.errors.schedule_ids }}
                </p>
                <div>
                    <label>Release date</label>
                    <input v-model="reschedule.release_date" type="date" required>
                    <p v-if="reschedule.errors.release_date" class="field-error">{{ reschedule.errors.release_date }}</p>
                </div>
                <div>
                    <label>Release location</label>
                    <input v-model="reschedule.release_location" required>
                    <p v-if="reschedule.errors.release_location" class="field-error">{{ reschedule.errors.release_location }}</p>
                </div>
                <div>
                    <label>Release method</label>
                    <select v-model="reschedule.release_method">
                        <option v-for="[value, label] in methodEntries" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label>Notes</label>
                    <textarea v-model="reschedule.notes" rows="2" />
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button class="btn-ghost" type="button" :disabled="reschedule.processing" @click="closeReschedule">Cancel</button>
                    <button class="btn-primary" type="submit" :disabled="reschedule.processing">
                        {{ rescheduleTargets.length > 1 ? `Reschedule ${rescheduleTargets.length} applicants` : 'Save new schedule' }}
                    </button>
                </div>
            </form>
        </Modal>

    </AdminLayout>
</template>
