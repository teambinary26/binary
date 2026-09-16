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

const props = defineProps({
    programs: { type: Object, required: true },
    programOptions: { type: Array, default: () => [] },
});

const showAdd = ref(false);
const pending = ref(null);
const removing = useForm({});

const form = useForm({
    assistance_program_id: props.programOptions[0]?.id || '',
    name: '',
    description: '',
    is_required: true,
});

const openAdd = (program = null) => {
    form.assistance_program_id = program?.id || form.assistance_program_id || props.programOptions[0]?.id || '';
    form.name = '';
    form.description = '';
    form.is_required = true;
    form.clearErrors();
    showAdd.value = true;
};

const closeAdd = () => {
    if (form.processing) {
        return;
    }

    showAdd.value = false;
};

const submit = () => form.post(route('admin.requirements.store'), {
    preserveScroll: true,
    onSuccess: () => {
        showAdd.value = false;
        form.name = '';
        form.description = '';
        form.is_required = true;
    },
});

const askRemove = (program, requirement) => {
    pending.value = { program, requirement };
};

const closeRemove = () => {
    if (removing.processing) {
        return;
    }

    pending.value = null;
};

const confirmRemove = () => {
    if (! pending.value) {
        return;
    }

    removing.delete(route('admin.requirements.destroy', pending.value.requirement.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Requirements" />
        <PageHeader title="Program Requirements" kicker="Documentary checklist by program">
            <template #actions>
                <button
                    v-if="can('programs.manage')"
                    class="btn-primary btn-sm"
                    type="button"
                    :disabled="!programOptions.length"
                    @click="openAdd()"
                >
                    Add requirement
                </button>
            </template>
        </PageHeader>

        <div v-if="!programs.data.length" class="panel px-4 py-6 text-sm text-gov-muted">
            No assistance programs are available yet.
        </div>

        <div v-for="program in programs.data" :key="program.id" class="panel mb-3">
            <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                <span>{{ program.name }}</span>
                <button
                    v-if="can('programs.manage')"
                    class="btn-ghost btn-sm"
                    type="button"
                    @click="openAdd(program)"
                >
                    Add to this program
                </button>
            </div>
            <ul v-if="program.requirements.length" class="divide-y divide-gov-border text-sm">
                <li
                    v-for="requirement in program.requirements"
                    :key="requirement.id"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-medium text-gov-dark">
                            <span>{{ requirement.name }}</span>
                            <span v-if="requirement.is_required" class="badge badge-danger">Required</span>
                            <span v-else class="badge">Optional</span>
                        </p>
                        <p v-if="requirement.description" class="mt-1 text-gov-muted">{{ requirement.description }}</p>
                    </div>
                    <button
                        v-if="can('programs.manage')"
                        class="btn-ghost btn-sm self-start sm:self-center"
                        type="button"
                        @click="askRemove(program, requirement)"
                    >
                        Remove
                    </button>
                </li>
            </ul>
            <p v-else class="px-4 py-4 text-sm text-gov-muted">No documentary requirements yet.</p>
        </div>
        <Pagination :paginator="programs" />

        <Modal :show="showAdd" title="Add requirement" @close="closeAdd">
            <form class="grid gap-3 p-4" @submit.prevent="submit">
                <div>
                    <label for="requirement-program">Program</label>
                    <select id="requirement-program" v-model="form.assistance_program_id" required>
                        <option value="" disabled>Select a program</option>
                        <option v-for="program in programOptions" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                    <p v-if="form.errors.assistance_program_id" class="field-error">{{ form.errors.assistance_program_id }}</p>
                </div>
                <div>
                    <label for="requirement-name">Requirement name</label>
                    <input id="requirement-name" v-model="form.name" placeholder="e.g. Valid ID, Proof of Residency" required>
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="requirement-description">Description</label>
                    <textarea id="requirement-description" v-model="form.description" rows="3" placeholder="What the applicant should submit" />
                    <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gov-text">
                    <input v-model="form.is_required" type="checkbox">
                    Required for filing
                </label>
                <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                    <button class="btn-ghost" type="button" :disabled="form.processing" @click="closeAdd">Cancel</button>
                    <button class="btn-primary" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Add requirement' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="Boolean(pending)"
            tone="danger"
            title="Remove requirement"
            confirm-label="Yes, remove"
            :processing="removing.processing"
            @cancel="closeRemove"
            @confirm="confirmRemove"
        >
            <p>
                Remove <strong>{{ pending?.requirement?.name }}</strong> from
                <strong>{{ pending?.program?.name }}</strong>?
            </p>
            <p class="text-gov-danger">This action cannot be undone.</p>
        </ConfirmModal>
    </AdminLayout>
</template>
