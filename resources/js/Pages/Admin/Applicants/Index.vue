<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

const props = defineProps({
    applicants: { type: Object, required: true },
    barangays: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = useForm({
    q: props.filters.q || '',
    type: props.filters.type || '',
    barangay: props.filters.barangay || '',
});

const pending = ref(null);
const deleting = useForm({});

const submit = () => form.get(route('admin.applicants.index'), { preserveState: true });

const askRemove = (row) => {
    if (! row.can_delete) {
        return;
    }

    pending.value = row;
};

const closeRemove = () => {
    if (deleting.processing) {
        return;
    }

    pending.value = null;
};

const confirmRemove = () => {
    if (! pending.value) {
        return;
    }

    deleting.delete(route('admin.applicants.destroy', pending.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Applicants" />
        <PageHeader title="Beneficiaries / Applicants" kicker="Registered citizens" />
        <form class="panel mb-4" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-4">
                <div><label>Search</label><input v-model="form.q"></div>
                <div>
                    <label>Type</label>
                    <select v-model="form.type">
                        <option value="">All</option>
                        <option value="student">Student</option>
                        <option value="non_student">Non-student</option>
                    </select>
                </div>
                <div>
                    <label>Barangay</label>
                    <select v-model="form.barangay">
                        <option value="">All</option>
                        <option v-for="barangay in barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                    </select>
                </div>
                <div class="flex items-end"><button class="btn-primary w-full" type="submit">Filter</button></div>
            </div>
        </form>
        <table class="data-table">
            <thead><tr><th>Beneficiary no.</th><th>Name</th><th>Type</th><th>Barangay</th><th>Applications</th><th>Action</th></tr></thead>
            <tbody>
                <tr v-for="row in applicants.data" :key="row.id">
                    <td data-label="No.">{{ row.applicant_no }}</td>
                    <td data-label="Name">{{ row.full_name }}</td>
                    <td data-label="Type">{{ row.beneficiary_label }}</td>
                    <td data-label="Barangay">{{ row.barangay }}</td>
                    <td data-label="Apps">{{ row.applications_count }}</td>
                    <td data-label="Action">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link :href="route('admin.applicants.show', row.id)">Open</Link>
                            <button
                                v-if="can('applicants.manage') && row.can_delete"
                                class="text-gov-danger hover:underline"
                                type="button"
                                @click="askRemove(row)"
                            >Delete</button>
                            <span v-else-if="can('applicants.manage')" class="text-xs text-gov-muted">Has applications</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <Pagination :links="applicants.links" />

        <ConfirmModal
            :show="Boolean(pending)"
            title="Delete beneficiary"
            :processing="deleting.processing"
            @cancel="closeRemove"
            @confirm="confirmRemove"
        >
            <p>
                Delete <strong>{{ pending?.full_name }}</strong>?
            </p>
            <p>This will permanently remove the applicant account.</p>
            <p class="text-gov-danger">This action cannot be undone.</p>
        </ConfirmModal>
    </AdminLayout>
</template>
