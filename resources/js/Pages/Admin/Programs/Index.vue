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

defineProps({
    programs: { type: Object, required: true },
});

const pending = ref(null);
const deleting = useForm({});

const askRemove = (program) => {
    if (! can('programs.manage') || ! program.can_delete) {
        return;
    }

    pending.value = program;
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

    deleting.delete(route('admin.programs.destroy', pending.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
    });
};
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
                    <td data-label="Action">
                        <div class="flex flex-wrap items-center gap-3">
                            <Link :href="route('admin.programs.edit', program.id)">Maintain</Link>
                            <button
                                v-if="can('programs.manage')"
                                class="text-gov-danger hover:underline disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50"
                                type="button"
                                :disabled="!program.can_delete"
                                :title="program.can_delete ? 'Delete this program' : 'This program has applications and cannot be deleted.'"
                                @click="askRemove(program)"
                            >Delete</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="programs" />

        <ConfirmModal
            :show="Boolean(pending)"
            tone="danger"
            title="Delete program"
            confirm-label="Yes, delete"
            :processing="deleting.processing"
            @cancel="closeRemove"
            @confirm="confirmRemove"
        >
            <p>
                Delete <strong>{{ pending?.name }}</strong> ({{ pending?.code }})?
            </p>
            <p>Requirements, form fields, and eligibility rules for this program will also be removed.</p>
            <p class="text-gov-danger">This action cannot be undone.</p>
        </ConfirmModal>
    </AdminLayout>
</template>
