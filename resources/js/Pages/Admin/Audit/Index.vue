<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
});

const form = useForm({
    q: props.filters.q || '',
    action: props.filters.action || '',
});

const submit = () => form.get(route('admin.audit-logs.index'), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <Head title="Audit Logs" />
        <PageHeader title="Audit Logs" kicker="Read-only administrative activity trail" />
        <form class="panel mb-4" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-3">
                <div><label>Search</label><input v-model="form.q"></div>
                <div>
                    <label>Action</label>
                    <select v-model="form.action">
                        <option value="">All</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                    </select>
                </div>
                <div class="flex items-end"><button class="btn-primary w-full" type="submit">Filter</button></div>
            </div>
        </form>
        <table class="data-table">
            <thead><tr><th>User</th><th>Action</th><th>Application</th><th>Date/Time</th><th>IP address</th><th>Description</th></tr></thead>
            <tbody>
                <tr v-for="log in logs.data" :key="log.id">
                    <td data-label="User">{{ log.user }}</td>
                    <td data-label="Action"><span class="text-xs font-bold uppercase tracking-wide text-gov-blue">{{ log.action }}</span></td>
                    <td data-label="Application">{{ log.application_no || '—' }}</td>
                    <td data-label="Date">{{ log.created_at }}</td>
                    <td data-label="IP">{{ log.ip_address }}</td>
                    <td data-label="Description">{{ log.description }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2 text-xs text-gov-muted">Audit records cannot be edited or deleted by administrators.</p>
        <Pagination :paginator="logs" />
    </AdminLayout>
</template>
