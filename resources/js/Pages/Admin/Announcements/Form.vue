<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    announcement: { type: Object, default: null },
    types: { type: Array, default: () => [] },
});

const form = useForm({
    title: props.announcement?.title || '',
    type: props.announcement?.type || 'notice',
    body: props.announcement?.body || '',
    is_published: props.announcement?.is_published ?? false,
});

const submit = () => {
    if (props.announcement?.id) {
        form.put(route('admin.announcements.update', props.announcement.id));
    } else {
        form.post(route('admin.announcements.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="announcement ? 'Edit Announcement' : 'New Announcement'" />
        <PageHeader :title="announcement ? 'Edit announcement' : 'New announcement'" />
        <form class="panel" @submit.prevent="submit">
            <div class="grid gap-4 p-4">
                <div><label>Title</label><input v-model="form.title" required></div>
                <div>
                    <label>Type</label>
                    <select v-model="form.type">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div><label>Body</label><textarea v-model="form.body" rows="8" required /></div>
                <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                    <input v-model="form.is_published" type="checkbox"> Publish to public website
                </label>
                <button class="btn-primary" type="submit">Save</button>
            </div>
        </form>
    </AdminLayout>
</template>
