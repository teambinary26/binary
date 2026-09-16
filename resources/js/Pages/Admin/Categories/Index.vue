<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    categories: { type: Object, required: true },
});

const form = useForm({
    name: '',
    group: 'student',
    description: '',
});

const submit = () => form.post(route('admin.categories.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});
</script>

<template>
    <AdminLayout>
        <Head title="Categories" />
        <PageHeader title="Program Categories" kicker="Student and general groups" />
        <form class="panel mb-4" @submit.prevent="submit">
            <div class="panel-h">Add category</div>
            <div class="grid gap-3 p-4 md:grid-cols-4">
                <input v-model="form.name" placeholder="Name" required>
                <select v-model="form.group">
                    <option value="student">Student</option>
                    <option value="general">General</option>
                </select>
                <input v-model="form.description" placeholder="Description">
                <button class="btn-primary" type="submit">Save</button>
            </div>
        </form>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Group</th><th>Programs</th><th>Status</th></tr></thead>
            <tbody>
                <tr v-for="category in categories.data" :key="category.id">
                    <td data-label="Name">{{ category.name }}</td>
                    <td data-label="Group">{{ category.group_label }}</td>
                    <td data-label="Programs">{{ category.programs_count }}</td>
                    <td data-label="Status">{{ category.is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="categories" />
    </AdminLayout>
</template>
