<script setup>
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

defineProps({
    announcements: { type: Object, required: true },
});
</script>

<template>
    <AdminLayout>
        <Head title="Announcements" />
        <PageHeader title="Announcements" kicker="Public notices">
            <template #actions>
                <Link v-if="can('announcements.manage')" class="btn-primary btn-sm" :href="route('admin.announcements.create')">Publish announcement</Link>
            </template>
        </PageHeader>
        <table class="data-table">
            <thead><tr><th>Title</th><th>Type</th><th>Published</th><th>Author</th><th /></tr></thead>
            <tbody>
                <tr v-for="row in announcements.data" :key="row.id">
                    <td data-label="Title">{{ row.title }}</td>
                    <td data-label="Type">{{ row.type_label }}</td>
                    <td data-label="Published">{{ row.published_at }}</td>
                    <td data-label="Author">{{ row.author }}</td>
                    <td data-label="Action"><Link :href="route('admin.announcements.edit', row.id)">Edit</Link></td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="announcements" />
    </AdminLayout>
</template>
