<script setup>
import { computed, ref } from 'vue';
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
const search = ref('');

const workflowForm = useForm({
    assignments: props.workflowSteps.map((step) => ({
        workflow_step: step.key,
        user_ids: (step.assigned_user_ids || []).map((id) => Number(id)),
    })),
});

const submit = () => form.put(route('admin.settings.update'));
const saveWorkflow = () => workflowForm.put(route('admin.settings.workflow'), { preserveScroll: true });

const stepTone = {
    1: 'border-t-blue-600',
    2: 'border-t-amber-500',
    3: 'border-t-green-600',
};

const stepDot = {
    1: 'bg-blue-600',
    2: 'bg-amber-500',
    3: 'bg-green-600',
};

const visibleStaff = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (! term) {
        return props.staff;
    }

    return props.staff.filter((member) => `${member.name} ${member.role}`.toLowerCase().includes(term));
});

const isAssigned = (stepIndex, userId) => workflowForm.assignments[stepIndex].user_ids.includes(Number(userId));

const toggleAssignment = (stepIndex, userId) => {
    const id = Number(userId);
    const current = workflowForm.assignments[stepIndex].user_ids;

    workflowForm.assignments[stepIndex].user_ids = current.includes(id)
        ? current.filter((value) => value !== id)
        : [...current, id];
};

const assignedNames = (stepIndex) => props.staff
    .filter((member) => isAssigned(stepIndex, member.id))
    .map((member) => member.name);

const initials = (name) => name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
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
            <div class="panel-body space-y-4">
                <p class="text-sm text-gov-muted">
                    Turn a step on for each person. They can open only the steps that are on. Steps that stay off are closed for them. Administrators can still open every step.
                </p>

                <div class="grid gap-3 md:grid-cols-3">
                    <section
                        v-for="(step, idx) in workflowSteps"
                        :key="`summary-${step.key}`"
                        class="border border-gov-border border-t-4 bg-gov-off px-3 py-3"
                        :class="stepTone[step.order]"
                    >
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gov-blue">{{ step.order }}. {{ step.label }}</p>
                        <p class="mt-1 text-xs text-gov-muted">{{ step.description }}</p>
                        <p class="mt-2 text-sm font-semibold text-gov-dark">
                            {{ workflowForm.assignments[idx].user_ids.length }} {{ workflowForm.assignments[idx].user_ids.length === 1 ? 'person' : 'people' }}
                        </p>
                        <p class="mt-1 text-xs text-gov-muted">
                            {{ assignedNames(idx).join(', ') || 'Nobody assigned yet' }}
                        </p>
                    </section>
                </div>

                <label class="block max-w-sm">
                    <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gov-muted">Find a person</span>
                    <input v-model="search" type="search" placeholder="Name or role">
                </label>

                <div class="hidden overflow-x-auto border border-gov-border md:block">
                    <table class="w-full min-w-[40rem] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gov-border bg-gov-off text-left">
                                <th class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-gov-muted">Person</th>
                                <th
                                    v-for="step in workflowSteps"
                                    :key="`head-${step.key}`"
                                    class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-gov-muted"
                                >
                                    <span class="inline-flex items-center gap-2">
                                        <span class="h-2 w-2" :class="stepDot[step.order]" />
                                        {{ step.label }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="! visibleStaff.length">
                                <td class="px-3 py-4 text-gov-muted" :colspan="workflowSteps.length + 1">No matching people.</td>
                            </tr>
                            <tr v-for="member in visibleStaff" :key="member.id" class="border-b border-gov-border last:border-b-0">
                                <td class="px-3 py-2">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center bg-gov-blue text-[11px] font-bold text-white">{{ initials(member.name) }}</span>
                                        <span>
                                            <span class="block font-semibold text-gov-dark">{{ member.name }}</span>
                                            <span class="block text-[11px] uppercase tracking-wide text-gov-muted">{{ member.role }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td v-for="(step, idx) in workflowSteps" :key="`${member.id}-${step.key}`" class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-w-24 cursor-pointer items-center justify-center border px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                        :class="isAssigned(idx, member.id) ? 'border-gov-blue bg-gov-blue text-white' : 'border-gov-border bg-white text-gov-muted'"
                                        :aria-pressed="isAssigned(idx, member.id)"
                                        @click="toggleAssignment(idx, member.id)"
                                    >
                                        {{ isAssigned(idx, member.id) ? 'Can open' : 'Closed' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    <p v-if="! visibleStaff.length" class="text-sm text-gov-muted">No matching people.</p>
                    <article v-for="member in visibleStaff" :key="`card-${member.id}`" class="border border-gov-border p-3">
                        <p class="font-semibold text-gov-dark">{{ member.name }}</p>
                        <p class="text-[11px] uppercase tracking-wide text-gov-muted">{{ member.role }}</p>
                        <div class="mt-3 grid gap-2">
                            <button
                                v-for="(step, idx) in workflowSteps"
                                :key="`card-${member.id}-${step.key}`"
                                type="button"
                                class="flex cursor-pointer items-center justify-between border px-3 py-2 text-left text-xs font-bold uppercase tracking-wide"
                                :class="isAssigned(idx, member.id) ? 'border-gov-blue bg-gov-blue text-white' : 'border-gov-border bg-white text-gov-muted'"
                                :aria-pressed="isAssigned(idx, member.id)"
                                @click="toggleAssignment(idx, member.id)"
                            >
                                <span>{{ step.label }}</span>
                                <span>{{ isAssigned(idx, member.id) ? 'Can open' : 'Closed' }}</span>
                            </button>
                        </div>
                    </article>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-gov-border p-4">
                <p v-if="workflowForm.errors.assignments" class="field-error">{{ workflowForm.errors.assignments }}</p>
                <button class="btn-primary ml-auto" type="submit" :disabled="workflowForm.processing">Save workflow assignments</button>
            </div>
        </form>
    </AdminLayout>
</template>
