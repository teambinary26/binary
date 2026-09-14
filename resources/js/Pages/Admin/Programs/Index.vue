<script setup>
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

defineProps({
    programs: { type: Object, required: true },
});
</script>

<template>
    <AdminLayout>
        <Head title="Assistance Programs" />
        <PageHeader title="Assistance Programs" kicker="Dynamic program catalog">
            <template #actions>
                <Link v-if="can('programs.manage')" class="btn-primary btn-sm" :href="route('admin.programs.create')">Create program</Link>
            </template>
        </PageHeader>
        <table class="data-table">
            <thead><tr><th>Code</th><th>Program</th><th>Category</th><th>Amount</th><th>Open</th><th>Applications</th><th /></tr></thead>
            <tbody>
                <tr v-for="program in programs.data" :key="program.id">
                    <td data-label="Code">{{ program.code }}</td>
                    <td data-label="Program">{{ program.name }}</td>
                    <td data-label="Category">{{ program.category?.name }}</td>
                    <td data-label="Amount">{{ program.amount_display }}</td>
                    <td data-label="Open">{{ program.is_open ? 'Yes' : 'No' }}</td>
                    <td data-label="Apps">{{ program.applications_count }}</td>
                    <td data-label="Action"><Link :href="route('admin.programs.edit', program.id)">Maintain</Link></td>
                </tr>
            </tbody>
        </table>
        <Pagination :links="programs.links" />
    </AdminLayout>
</template>
