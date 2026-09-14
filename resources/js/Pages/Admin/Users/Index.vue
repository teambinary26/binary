<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

defineProps({
    users: { type: Object, required: true },
});

const pending = ref(null);
const deleting = useForm({});

const modalTitle = computed(() => (
    pending.value?.is_applicant ? 'Delete applicant account' : 'Delete user'
));

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

    deleting.delete(route('admin.users.destroy', pending.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Users" />
        <PageHeader title="System Users" kicker="Staff and applicant accounts">
            <template #actions>
                <Link v-if="can('users.manage')" class="btn-primary btn-sm" :href="route('admin.users.create')">Create staff user</Link>
            </template>
        </PageHeader>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Office</th><th>Active</th><th>Last login</th><th>Action</th></tr></thead>
            <tbody>
                <tr v-for="row in users.data" :key="row.id">
                    <td data-label="Name">{{ row.name }}</td>
                    <td data-label="Email">{{ row.email }}</td>
                    <td data-label="Role">{{ row.role }}</td>
                    <td data-label="Office">{{ row.office || '—' }}</td>
                    <td data-label="Active">{{ row.is_active ? 'Yes' : 'No' }}</td>
                    <td data-label="Login">{{ row.last_login_at }}</td>
                    <td data-label="Action">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link v-if="can('users.manage') && row.is_staff" :href="route('admin.users.edit', row.id)">Edit</Link>
                            <button
                                v-if="can('users.manage') && row.can_delete"
                                class="text-gov-danger hover:underline"
                                type="button"
                                @click="askRemove(row)"
                            >Delete</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <Pagination :links="users.links" />

        <ConfirmModal
            :show="Boolean(pending)"
            :title="modalTitle"
            :processing="deleting.processing"
            @cancel="closeRemove"
            @confirm="confirmRemove"
        >
            <p>
                Delete <strong>{{ pending?.name }}</strong>?
            </p>
            <p v-if="pending?.is_applicant">
                This will permanently remove the applicant account and all connected records, including profile, applications, documents, and releases.
            </p>
            <p v-else>
                This will permanently remove this user account.
            </p>
            <p class="text-gov-danger">This action cannot be undone.</p>
        </ConfirmModal>
    </AdminLayout>
</template>
