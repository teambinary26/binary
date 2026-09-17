<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    staff: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.staff?.name || '',
    email: props.staff?.email || '',
    employee_no: props.staff?.employee_no || '',
    office: props.staff?.office || '',
    role_id: props.staff?.role_id || props.roles[0]?.id || '',
    is_active: props.staff?.is_active ?? true,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    if (props.staff?.id) {
        form.put(route('admin.users.update', props.staff.id));
    } else {
        form.post(route('admin.users.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="staff ? 'Edit User' : 'Create User'" />
        <PageHeader :title="staff ? 'Edit user' : 'Create user'" />
        <form class="panel" @submit.prevent="submit">
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <div><label>Name</label><input v-model="form.name" required></div>
                <div><label>Email</label><input v-model="form.email" type="email" required></div>
                <div><label>Employee no.</label><input v-model="form.employee_no"></div>
                <div><label>Office</label><input v-model="form.office"></div>
                <div>
                    <label>Role</label>
                    <select v-model="form.role_id" required>
                        <option disabled value="">Select role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                            {{ role.slug === 'sk' ? 'Sangguniang Kabataan (SK)' : role.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gov-muted">Administrator creates another admin. SK is assigned to document verification.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                    <input v-model="form.is_active" type="checkbox"> Active
                </label>
                <div>
                    <label>Password {{ staff ? '(leave blank to keep)' : '' }}</label>
                    <input v-model="form.password" type="password" :required="!staff">
                </div>
                <div>
                    <label>Confirm password</label>
                    <input v-model="form.password_confirmation" type="password">
                </div>
            </div>
            <div class="border-t border-gov-border p-4"><button class="btn-primary" type="submit">Save user</button></div>
        </form>
    </AdminLayout>
</template>
