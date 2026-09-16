<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ApplySteps from '@/Components/ApplySteps.vue';
import ProgramFormFields from '@/Components/ProgramFormFields.vue';

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
const stepCount = computed(() => (props.fields.length ? 3 : 2));
const isStudent = computed(() => props.application.applicant_detail?.beneficiary_type !== 'non_student');
const disabledStudentFieldNames = computed(() => (isStudent.value ? [] : [
    'school_name',
    'course_or_program',
    'year_level',
    'education_level',
    'student_id_no',
    'student_number',
    'student_id',
    'grade_level',
    'tuition_amount',
    'term',
    'supplies_needed',
    'school_location',
    'usual_transport',
    'estimated_daily_fare',
]));

const submit = () => form.post(route('applicant.apply.form.store', props.application.id));
</script>

<template>
    <ApplicantLayout>
        <Head title="Application Form" />
        <PageHeader :title="application.program?.name" :kicker="`Step 1 of ${stepCount} — Application form`" :document-no="application.application_no" />
        <ApplySteps :application="application" />
        <form class="panel" @submit.prevent="submit">
            <div class="panel-h">Program-specific information</div>
            <div class="p-3 sm:p-4">
                <p class="mb-4 text-sm text-gov-muted">Answer the questions set for this program. Your answers appear in review, staff evaluation, and document matching.</p>
                <ProgramFormFields :fields="fields" :model="form" :errors="form.errors" :disabled-names="disabledStudentFieldNames" />
            </div>
            <div class="border-t border-gov-border p-4">
                <button class="btn-primary w-full sm:w-auto" type="submit" :disabled="form.processing">Save and continue</button>
            </div>
        </form>
    </ApplicantLayout>
</template>
