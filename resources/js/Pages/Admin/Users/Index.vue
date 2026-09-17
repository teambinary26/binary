<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

const props = defineProps({
    administrators: { type: Object, required: true },
    sk: { type: Object, required: true },
    staff: { type: Object, required: true },
    applicants: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const groups = computed(() => [
    { key: 'administrators', title: 'Administrators', paginator: props.administrators },
    { key: 'sk', title: 'Sangguniang Kabataan', paginator: props.sk },
    { key: 'staff', title: 'Staff', paginator: props.staff },
    { key: 'applicants', title: 'Applicants', paginator: props.applicants },
]);

const showCreate = ref(false);
const pending = ref(null);
const deleting = useForm({});

const form = useForm({
    name: '',
    email: '',
    employee_no: '',
    office: '',
    role_id: '',
    is_active: true,
    password: '',
    password_confirmation: '',
});

const resetForm = () => {
    form.name = '';
    form.email = '';
    form.employee_no = '';
    form.office = '';
    form.role_id = '';
    form.is_active = true;
    form.password = '';
    form.password_confirmation = '';
    form.clearErrors();
};

const openCreate = () => {
    resetForm();
    showCreate.value = true;
};

const closeCreate = () => {
    if (form.processing) {
        return;
    }

    showCreate.value = false;
};

const submitCreate = () => form.post(route('admin.users.store'), {
    preserveScroll: true,
    onSuccess: () => {
        showCreate.value = false;
        resetForm();
    },
});

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
        <PageHeader title="System Users" kicker="Administrator, SK, staff, and applicant accounts">
            <template #actions>
                <button
                    v-if="can('users.manage')"
                    class="btn-primary btn-sm"
                    type="button"
                    @click="openCreate"
                >
                    Create user
                </button>
            </template>
        </PageHeader>

        <section v-for="group in groups" :key="group.key" class="panel mb-4">
            <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                <span>{{ group.title }}</span>
                <span class="text-xs font-bold text-gov-muted">{{ group.paginator.total }} account(s)</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Office</th>
                        <th>Active</th>
                        <th>Last login</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!group.paginator.data.length">
                        <td class="px-4 py-6 text-sm text-gov-muted" colspan="7">No {{ group.title.toLowerCase() }} yet.</td>
                    </tr>
                    <tr v-for="row in group.paginator.data" :key="row.id">
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
            <div class="px-4 pb-4"><Pagination :paginator="group.paginator" /></div>
        </section>

        <Modal :show="showCreate" title="Create user" wide @close="closeCreate">
            <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="submitCreate">
                <div>
                    <label for="user-name">Name</label>
                    <input id="user-name" v-model="form.name" required>
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="user-email">Email</label>
                    <input id="user-email" v-model="form.email" type="email" required>
                    <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="user-employee">Employee no.</label>
                    <input id="user-employee" v-model="form.employee_no">
                    <p v-if="form.errors.employee_no" class="field-error">{{ form.errors.employee_no }}</p>
                </div>
                <div>
                    <label for="user-office">Office</label>
                    <input id="user-office" v-model="form.office">
                    <p v-if="form.errors.office" class="field-error">{{ form.errors.office }}</p>
                </div>
                <div class="md:col-span-2">
                    <label for="user-role">Role</label>
                    <select id="user-role" v-model="form.role_id" required>
                        <option disabled value="">Select role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                            {{ role.slug === 'sk' ? 'Sangguniang Kabataan (SK)' : role.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gov-muted">
                        Choose Administrator to create another admin. Sangguniang Kabataan accounts are assigned to document verification.
                    </p>
                    <p v-if="form.errors.role_id" class="field-error">{{ form.errors.role_id }}</p>
                </div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm font-normal normal-case tracking-normal">
                    <input v-model="form.is_active" type="checkbox"> Active
                </label>
                <div>
                    <label for="user-password">Password</label>
                    <PasswordInput id="user-password" v-model="form.password" autocomplete="new-password" required />
                    <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label for="user-password-confirmation">Confirm password</label>
                    <PasswordInput id="user-password-confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gov-border pt-4 md:col-span-2 sm:flex-row sm:justify-end">
                    <button class="btn-ghost" type="button" :disabled="form.processing" @click="closeCreate">Cancel</button>
                    <button class="btn-primary" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save user' }}
                    </button>
                </div>
            </form>
        </Modal>

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
