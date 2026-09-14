<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    programs: { type: Array, default: () => [] },
});

const form = useForm({
    assistance_program_id: props.programs[0]?.id || '',
    name: '',
    description: '',
    is_required: true,
});

const submit = () => form.post(route('admin.requirements.store'), {
    preserveScroll: true,
    onSuccess: () => {
        form.name = '';
        form.description = '';
    },
});

const remove = (id) => useForm({}).delete(route('admin.requirements.destroy', id), { preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head title="Requirements" />
        <PageHeader title="Program Requirements" kicker="Documentary checklist by program" />
        <form class="panel mb-4" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-4">
                <select v-model="form.assistance_program_id" required>
                    <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                </select>
                <input v-model="form.name" placeholder="Requirement name" required>
                <input v-model="form.description" placeholder="Description">
                <button class="btn-primary" type="submit">Add</button>
            </div>
        </form>
        <div v-for="program in programs" :key="program.id" class="panel mb-3">
            <div class="panel-h">{{ program.name }}</div>
            <ul class="divide-y divide-gov-border text-sm">
                <li v-for="requirement in program.requirements" :key="requirement.id" class="flex items-center justify-between px-4 py-2">
                    <span>{{ requirement.name }} <span v-if="requirement.is_required" class="badge badge-danger">Required</span></span>
                    <button class="btn-ghost btn-sm" type="button" @click="remove(requirement.id)">Remove</button>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>
