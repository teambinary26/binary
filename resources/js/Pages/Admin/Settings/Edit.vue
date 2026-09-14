<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    workflowSteps: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
});

const form = useForm({ ...props.settings });
const wide = ['address', 'office_hours'];
const label = (key) => key.replaceAll('_', ' ');

const workflowForm = useForm({
    assignments: props.workflowSteps.map((step) => ({
        workflow_step: step.key,
        user_ids: (step.assigned_user_ids || []).map((id) => Number(id)),
    })),
});

const submit = () => form.put(route('admin.settings.update'));
const saveWorkflow = () => workflowForm.put(route('admin.settings.workflow'), { preserveScroll: true });

const stepColors = {
    1: 'bg-blue-600',
    2: 'bg-amber-500',
    3: 'bg-green-600',
};
</script>

<template>
    <AdminLayout>
        <Head title="System Settings" />
        <PageHeader title="System Settings" kicker="Office identity and workflow configuration" />

        <!-- ═══════════════════════════════════════════
             SECTION 1: Office Identity
             ═══════════════════════════════════════════ -->
        <form class="panel mb-6" @submit.prevent="submit">
            <div class="panel-h">Office Identity</div>
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <div v-for="(_, key) in settings" :key="key" :class="{ 'md:col-span-2': wide.includes(key) }">
                    <label>{{ label(key) }}</label>
                    <input v-model="form[key]" required>
                </div>
            </div>
            <div class="border-t border-gov-border p-4"><button class="btn-primary" type="submit">Save settings</button></div>
        </form>

        <!-- ═══════════════════════════════════════════
             SECTION 2: Workflow Staff Assignment
             ═══════════════════════════════════════════ -->
        <form class="panel" @submit.prevent="saveWorkflow">
            <div class="panel-h">Workflow Staff Assignment</div>
            <div class="panel-body space-y-2">
                <p class="text-sm text-gov-muted">
                    Assign one or more staff members to each workflow step. Anyone assigned to a step (and administrators) can work on applications in that step.
                </p>

                <div class="my-4 overflow-hidden rounded-lg border border-gov-border bg-gov-off">
                    <div class="relative grid grid-cols-1 md:grid-cols-3">
                        <div class="pointer-events-none absolute left-[16.66%] right-[16.66%] top-9 hidden h-1 bg-gray-300 md:block" />
                        <div
                            v-for="(step, idx) in workflowSteps"
                            :key="step.key"
                            class="relative z-10 flex flex-col items-center border-gov-border p-5 text-center md:border-r last:md:border-r-0"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white" :class="stepColors[step.order]">
                                {{ step.order }}
                            </div>
                            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-gov-blue">{{ step.label }}</p>
                            <p class="mt-1 min-h-[2.5rem] max-w-[12rem] text-[11px] leading-tight text-gov-muted">{{ step.description }}</p>
                            <div class="mt-3 max-h-40 w-full max-w-xs overflow-y-auto border border-gov-border bg-white text-left">
                                <label
                                    v-for="member in staff"
                                    :key="member.id"
                                    class="flex items-start gap-2 border-b border-gov-border px-2 py-1.5 text-xs last:border-b-0"
                                >
                                    <input v-model="workflowForm.assignments[idx].user_ids" type="checkbox" :value="member.id" class="mt-0.5">
                                    <span>
                                        <span class="font-semibold text-gov-dark">{{ member.name }}</span>
                                        <span class="block text-[10px] uppercase tracking-wide text-gov-muted">{{ member.role }}</span>
                                    </span>
                                </label>
                            </div>
                            <p class="mt-1 text-[11px] text-gov-muted">
                                {{ workflowForm.assignments[idx].user_ids.length || 0 }} selected
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gov-border p-4">
                <button class="btn-primary" type="submit" :disabled="workflowForm.processing">Save Workflow Assignments</button>
            </div>
        </form>
    </AdminLayout>
</template>
