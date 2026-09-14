<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ApplySteps from '@/Components/ApplySteps.vue';

const props = defineProps({
    application: { type: Object, required: true },
    fields: { type: Array, default: () => [] },
    answers: { type: Object, default: () => ({}) },
});

const initial = {};
props.fields.forEach((field) => {
    initial[field.name] = props.answers[field.name] ?? '';
});
const form = useForm(initial);

const submit = () => form.post(route('applicant.apply.form.store', props.application.id));
</script>

<template>
    <ApplicantLayout>
        <Head title="Application Form" />
        <PageHeader :title="application.program?.name" kicker="Step 2 of 4 — Application form" :document-no="application.application_no" />
        <ApplySteps :application-id="application.id" />
        <form class="panel" @submit.prevent="submit">
            <div class="panel-h">Program-specific information</div>
            <div class="grid gap-4 p-3 md:grid-cols-2 sm:p-4">
                <div v-for="field in fields" :key="field.name" :class="{ 'md:col-span-2': field.type === 'textarea' }">
                    <label :for="`field_${field.name}`">{{ field.label }} <template v-if="field.is_required">*</template></label>
                    <textarea
                        v-if="field.type === 'textarea'"
                        :id="`field_${field.name}`"
                        v-model="form[field.name]"
                        rows="4"
                        :required="field.is_required"
                    />
                    <select
                        v-else-if="field.type === 'select'"
                        :id="`field_${field.name}`"
                        v-model="form[field.name]"
                        :required="field.is_required"
                    >
                        <option value="">Select</option>
                        <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                    </select>
                    <input
                        v-else
                        :id="`field_${field.name}`"
                        v-model="form[field.name]"
                        :type="field.type"
                        :required="field.is_required"
                    >
                    <p v-if="field.help_text" class="mt-1 text-xs text-gov-muted">{{ field.help_text }}</p>
                </div>
            </div>
            <div class="border-t border-gov-border p-4">
                <button class="btn-primary w-full sm:w-auto" type="submit" :disabled="form.processing">Save and continue</button>
            </div>
        </form>
    </ApplicantLayout>
</template>
