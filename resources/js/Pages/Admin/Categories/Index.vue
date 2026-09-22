<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

const { can } = useCan();

defineProps({
    categories: { type: Object, required: true },
});

const form = useForm({
    name: '',
    group: 'student',
    description: '',
});

const editing = ref(null);
const editForm = useForm({
    name: '',
    group: 'student',
    description: '',
    is_active: true,
});

const pending = ref(null);
const deleting = useForm({});

const submit = () => form.post(route('admin.categories.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const openEdit = (category) => {
    editing.value = category;
    editForm.name = category.name;
    editForm.group = category.group;
    editForm.description = category.description || '';
    editForm.is_active = Boolean(category.is_active);
    editForm.clearErrors();
};

const closeEdit = () => {
    if (editForm.processing) {
        return;
    }

    editing.value = null;
};

const saveEdit = () => {
    if (! editing.value) {
        return;
    }

    editForm.put(route('admin.categories.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const askRemove = (category) => {
    if (! can('programs.manage') || ! category.can_delete) {
        return;
    }

    pending.value = category;
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

    deleting.delete(route('admin.categories.destroy', pending.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Categories" />
        <PageHeader title="Program Categories" kicker="Student and general groups" />
        <form v-if="can('programs.manage')" class="panel mb-4" @submit.prevent="submit">
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
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Group</th>
                    <th>Programs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="category in categories.data" :key="category.id">
                    <td data-label="Name">{{ category.name }}</td>
                    <td data-label="Group">{{ category.group_label }}</td>
                    <td data-label="Programs">{{ category.programs_count }}</td>
                    <td data-label="Status">{{ category.is_active ? 'Active' : 'Inactive' }}</td>
                    <td data-label="Actions">
                        <div v-if="can('programs.manage')" class="flex flex-wrap items-center gap-3">
                            <button class="text-gov-blue hover:underline" type="button" @click="openEdit(category)">Edit</button>
                            <button
                                class="text-gov-danger hover:underline disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50"
                                type="button"
                                :disabled="!category.can_delete"
                                :title="category.can_delete ? 'Delete this category' : 'This category has programs and cannot be deleted.'"
                                @click="askRemove(category)"
                            >Delete</button>
                        </div>
                        <span v-else class="text-gov-muted">—</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <Pagination :paginator="categories" />

        <Modal :show="Boolean(editing)" title="Edit category" @close="closeEdit">
            <form class="grid gap-3 p-4" @submit.prevent="saveEdit">
                <div>
                    <label for="edit-category-name">Name</label>
                    <input id="edit-category-name" v-model="editForm.name" required>
                    <p v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label for="edit-category-group">Group</label>
                    <select id="edit-category-group" v-model="editForm.group">
                        <option value="student">Student</option>
                        <option value="general">General</option>
                    </select>
                    <p v-if="editForm.errors.group" class="field-error">{{ editForm.errors.group }}</p>
                </div>
                <div>
                    <label for="edit-category-description">Description</label>
                    <input id="edit-category-description" v-model="editForm.description" placeholder="Description">
                    <p v-if="editForm.errors.description" class="field-error">{{ editForm.errors.description }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gov-text">
                    <input v-model="editForm.is_active" type="checkbox">
                    Active
                </label>
                <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                    <button class="btn-ghost" type="button" :disabled="editForm.processing" @click="closeEdit">Cancel</button>
                    <button class="btn-primary" type="submit" :disabled="editForm.processing">
                        {{ editForm.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(pending)"
            tone="danger"
            title="Delete category"
            confirm-label="Yes, delete"
            :processing="deleting.processing"
            @cancel="closeRemove"
            @confirm="confirmRemove"
        >
            <p>
                Delete <strong>{{ pending?.name }}</strong>?
            </p>
            <p class="text-gov-danger">This action cannot be undone.</p>
        </ConfirmModal>
    </AdminLayout>
</template>
