<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    applications: { type: Object, required: true },
    programs: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    tab: { type: String, default: 'all' },
    counts: {
        type: Object,
        default: () => ({ total: 0, incoming: 0, in_progress: 0, approved: 0, rejected: 0 }),
    },
    canDelete: { type: Boolean, default: false },
    canAccept: { type: Boolean, default: false },
});

const isIncoming = computed(() => props.tab === 'incoming');

const form = useForm({
    q: props.filters.q || '',
    status: props.filters.status || '',
    program: props.filters.program || '',
    tab: props.tab || 'all',
});

let searchTimer = null;

const applyFilters = () => {
    form.get(route('admin.applications.index'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(() => form.q, (value) => {
    clearTimeout(searchTimer);
    if (! value) {
        applyFilters();
        return;
    }
    searchTimer = setTimeout(applyFilters, 300);
});

watch(() => [form.status, form.program, form.tab], () => {
    clearTimeout(searchTimer);
    applyFilters();
});

const switchTab = (tab) => {
    if (form.tab === tab) {
        return;
    }

    selectedIds.value = [];
    form.tab = tab;
    if (tab === 'incoming') {
        form.status = '';
    }
};

const filterByStatus = (statusValue) => {
    selectedIds.value = [];
    form.tab = 'all';
    form.status = statusValue;
};

const pageIds = computed(() => (props.applications.data ?? []).map((row) => Number(row.id)));
const selectedIds = ref([]);

watch(pageIds, (ids) => {
    selectedIds.value = selectedIds.value.filter((id) => ids.includes(id));
});

const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)));
const someSelected = computed(() => selectedIds.value.length > 0 && ! allSelected.value);
const selectedRows = computed(() => (props.applications.data ?? []).filter((row) => selectedIds.value.includes(Number(row.id))));

const isSelected = (id) => selectedIds.value.includes(Number(id));

const toggleRow = (id) => {
    const value = Number(id);
    if (isSelected(value)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== value);
        return;
    }

    selectedIds.value = [...selectedIds.value, value];
};

const toggleAll = () => {
    selectedIds.value = allSelected.value ? [] : [...pageIds.value];
};

const pendingDelete = ref(null);
const pendingBulk = ref(false);
const deleting = ref(false);

const requestDelete = (row) => {
    pendingBulk.value = false;
    pendingDelete.value = row;
};

const requestBulkDelete = () => {
    if (! selectedRows.value.length) {
        return;
    }

    pendingBulk.value = true;
    pendingDelete.value = { count: selectedRows.value.length };
};

const cancelDelete = () => {
    if (deleting.value) return;
    pendingDelete.value = null;
    pendingBulk.value = false;
};

const confirmDelete = () => {
    if (pendingBulk.value) {
        if (! selectedIds.value.length) return;
        deleting.value = true;
        router.post(route('admin.applications.bulk-destroy'), { ids: selectedIds.value }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
            onFinish: () => {
                deleting.value = false;
                pendingDelete.value = null;
                pendingBulk.value = false;
            },
        });
        return;
    }

    if (! pendingDelete.value) return;
    deleting.value = true;
    router.delete(route('admin.applications.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
};

const deleteMessage = computed(() => {
    if (pendingBulk.value) {
        const count = pendingDelete.value?.count ?? selectedIds.value.length;
        return `Delete ${count} selected application${count === 1 ? '' : 's'}? This action cannot be undone.`;
    }

    if (! pendingDelete.value) {
        return '';
    }

    return `Are you sure you want to delete application ${pendingDelete.value.application_no}? This action cannot be undone.`;
});

const reviewForm = useForm({ remarks: '' });
const pendingReview = ref(null);

const requestAccept = (row) => {
    pendingReview.value = { row, action: 'accept' };
    reviewForm.remarks = '';
    reviewForm.clearErrors();
};

const requestReject = (row) => {
    pendingReview.value = { row, action: 'reject' };
    reviewForm.remarks = '';
    reviewForm.clearErrors();
};

const cancelReview = () => {
    if (reviewForm.processing) return;
    pendingReview.value = null;
    reviewForm.clearErrors();
};

const confirmReview = () => {
    if (! pendingReview.value || reviewForm.processing) {
        return;
    }

    if (pendingReview.value.action === 'reject' && ! reviewForm.remarks?.trim()) {
        reviewForm.setError('remarks', 'Please provide a reason for rejecting the application.');
        return;
    }

    const routeName = pendingReview.value.action === 'accept'
        ? 'admin.applications.approve-applicant'
        : 'admin.applications.reject-applicant';

    reviewForm.post(route(routeName, pendingReview.value.row.id), {
        preserveScroll: true,
        onFinish: () => {
            pendingReview.value = null;
            reviewForm.reset('remarks');
        },
    });
};

const acceptMessage = computed(() => {
    const row = pendingReview.value?.row;
    if (! row) {
        return '';
    }

    const name = row.applicant?.full_name || 'this applicant';

    return `Accept ${name} so they can continue this application? Sign-in details will be emailed if the account is still pending.`;
});

const cards = [
    { key: 'total', label: 'Total', tone: 'blue', filter: '' },
    { key: 'in_progress', label: 'In progress', tone: 'yellow', filter: 'under_verification' },
    { key: 'approved', label: 'Approved', tone: 'green', filter: 'approved' },
    { key: 'rejected', label: 'Rejected / Cancelled', tone: 'red', filter: 'rejected' },
];

const toneClass = (tone) => ({
    blue: 'border-l-gov-blue text-gov-blue',
    gray: 'border-l-gray-400 text-gray-600',
    yellow: 'border-l-gov-warning text-yellow-700',
    green: 'border-l-gov-success text-green-700',
    red: 'border-l-gov-danger text-red-700',
}[tone] || 'border-l-gov-blue text-gov-blue');

const emptyMessage = computed(() => (
    isIncoming.value
        ? 'No incoming applications are waiting for acceptance.'
        : 'No applications match the current filters.'
));
</script>

<template>
    <AdminLayout>
        <Head :title="isIncoming ? 'Incoming applications' : 'Applications'" />
        <PageHeader
            :title="isIncoming ? 'Incoming Applications' : 'Application Management'"
            :kicker="isIncoming ? 'Accept or reject new filings' : 'All filings'"
        />

        <div class="mb-4 flex flex-wrap border-b-2 border-gov-border" role="tablist" aria-label="Application lists">
            <button
                type="button"
                role="tab"
                class="relative -mb-[2px] px-4 py-2.5 text-sm font-bold uppercase tracking-wide"
                :class="! isIncoming ? 'border-b-2 border-gov-blue text-gov-blue' : 'text-gov-muted hover:text-gov-dark'"
                :aria-selected="(! isIncoming).toString()"
                @click="switchTab('all')"
            >
                All applications
            </button>
            <button
                type="button"
                role="tab"
                class="relative -mb-[2px] inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold uppercase tracking-wide"
                :class="isIncoming ? 'border-b-2 border-gov-blue text-gov-blue' : 'text-gov-muted hover:text-gov-dark'"
                :aria-selected="isIncoming.toString()"
                @click="switchTab('incoming')"
            >
                Incoming
                <span class="inline-flex min-w-[1.25rem] items-center justify-center bg-gov-warning px-1.5 py-0.5 text-[11px] font-bold text-white">
                    {{ counts.incoming ?? 0 }}
                </span>
            </button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4">
            <button
                v-for="card in cards"
                :key="card.key"
                type="button"
                class="panel flex flex-col items-start gap-1 border-l-4 p-3 text-left transition-colors hover:bg-gov-off"
                :class="toneClass(card.tone)"
                @click="filterByStatus(card.filter)"
            >
                <span class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">{{ card.label }}</span>
                <span class="text-2xl font-extrabold text-gov-dark">{{ counts[card.key] ?? 0 }}</span>
            </button>
        </div>

        <p v-if="isIncoming" class="mb-4 text-sm text-gov-muted">
            New applications stay here until the office accepts or rejects them. Accepted filings move to All applications.
        </p>

        <form class="panel mb-4" @submit.prevent="applyFilters">
            <div class="grid gap-3 p-4" :class="isIncoming ? 'md:grid-cols-2' : 'md:grid-cols-3'">
                <div><label>Search</label><input v-model="form.q" placeholder="Name or number"></div>
                <div v-if="! isIncoming">
                    <label>Status</label>
                    <select v-model="form.status">
                        <option value="">All</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div>
                    <label>Program</label>
                    <select v-model="form.program">
                        <option value="">All</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                </div>
            </div>
        </form>

        <div
            v-if="canDelete"
            class="mb-3 flex flex-wrap items-center justify-between gap-2 border border-gov-border bg-white px-4 py-3"
        >
            <p class="text-sm text-gov-text">
                <span v-if="selectedIds.length">{{ selectedIds.length }} selected on this page</span>
                <span v-else>Select applications to delete them together.</span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button class="btn-secondary btn-sm" type="button" :disabled="!pageIds.length" @click="toggleAll">
                    {{ allSelected ? 'Clear selection' : 'Select all' }}
                </button>
                <button
                    class="btn-danger btn-sm"
                    type="button"
                    :disabled="!selectedIds.length"
                    @click="requestBulkDelete"
                >
                    Delete selected{{ selectedIds.length ? ` (${selectedIds.length})` : '' }}
                </button>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th v-if="canDelete" class="w-12">
                        <label class="inline-flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                :disabled="!pageIds.length"
                                @change="toggleAll"
                            >
                            <span class="sr-only">Select all on this page</span>
                        </label>
                    </th>
                    <th>Application no.</th>
                    <th>Applicant</th>
                    <th>Program</th>
                    <th>Category</th>
                    <th>Date applied</th>
                    <th>Status</th>
                    <th>Assigned staff</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in applications.data" :key="row.id">
                    <td v-if="canDelete" data-label="Select">
                        <input type="checkbox" :checked="isSelected(row.id)" @change="toggleRow(row.id)">
                    </td>
                    <td data-label="No.">{{ row.application_no }}</td>
                    <td data-label="Applicant">{{ row.applicant?.full_name }}</td>
                    <td data-label="Program">{{ row.program?.name }}</td>
                    <td data-label="Category">{{ row.program?.category_name }}</td>
                    <td data-label="Date">{{ row.submitted_at }}</td>
                    <td data-label="Status"><StatusBadge :label="row.status_label" :tone="row.status_tone" /></td>
                    <td data-label="Staff">{{ row.assigned_staff || '—' }}</td>
                    <td data-label="Action" class="whitespace-nowrap text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Link class="btn-secondary btn-sm" :href="route('admin.applications.show', row.id)">Open</Link>
                            <template v-if="isIncoming && canAccept">
                                <button
                                    class="btn-success btn-sm"
                                    type="button"
                                    :disabled="reviewForm.processing"
                                    @click="requestAccept(row)"
                                >Accept</button>
                                <button
                                    class="btn-danger btn-sm"
                                    type="button"
                                    :disabled="reviewForm.processing"
                                    @click="requestReject(row)"
                                >Reject</button>
                            </template>
                            <button
                                v-if="canDelete"
                                type="button"
                                class="btn-danger btn-sm"
                                @click="requestDelete(row)"
                            >Delete</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!applications.data.length">
                    <td :colspan="canDelete ? 9 : 8" class="px-4 py-6 text-center text-sm text-gov-muted">{{ emptyMessage }}</td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="applications" />

        <ConfirmModal
            :show="!!pendingDelete"
            tone="danger"
            :title="pendingBulk ? 'Delete selected applications' : 'Delete application'"
            :message="deleteMessage"
            confirm-label="Yes, delete"
            cancel-label="Cancel"
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />

        <ConfirmModal
            :show="pendingReview?.action === 'accept'"
            tone="success"
            title="Accept application"
            :message="acceptMessage"
            confirm-label="Accept application"
            cancel-label="Cancel"
            :processing="reviewForm.processing"
            @confirm="confirmReview"
            @cancel="cancelReview"
        />

        <Modal :show="pendingReview?.action === 'reject'" title="Reject application" @close="cancelReview">
            <form class="space-y-4 p-4" @submit.prevent="confirmReview">
                <p class="text-sm text-gov-text">
                    Reject application {{ pendingReview?.row?.application_no }}?
                    The applicant will be notified by email with your remarks.
                </p>
                <div>
                    <label for="incoming-reject-remarks">Reason for rejection</label>
                    <textarea
                        id="incoming-reject-remarks"
                        v-model="reviewForm.remarks"
                        rows="3"
                        required
                        placeholder="Required"
                    />
                    <p v-if="reviewForm.errors.remarks" class="field-error">{{ reviewForm.errors.remarks }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button class="btn-ghost" type="button" :disabled="reviewForm.processing" @click="cancelReview">Cancel</button>
                    <button class="btn-danger" type="submit" :disabled="reviewForm.processing">Yes, reject</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
