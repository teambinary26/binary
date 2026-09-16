<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    applicant: { type: Object, required: true },
    barangays: { type: Array, default: () => [] },
});

const form = useForm({
    full_name: props.applicant.full_name,
    date_of_birth: props.applicant.date_of_birth_raw,
    sex: props.applicant.sex,
    contact_number: props.applicant.contact_number,
    beneficiary_type: props.applicant.beneficiary_type,
    street: props.applicant.street,
    barangay: props.applicant.barangay,
    municipality: props.applicant.municipality,
    province: props.applicant.province,
    school_name: props.applicant.school_name || '',
    course_or_program: props.applicant.course_or_program || '',
    year_level: props.applicant.year_level || '',
});

const isStudent = computed(() => form.beneficiary_type === 'student');

watch(() => form.beneficiary_type, (type) => {
    if (type === 'non_student') {
        form.school_name = '';
        form.course_or_program = '';
        form.year_level = '';
    }
});

const submit = () => form.put(route('applicant.profile.update'));
</script>

<template>
    <ApplicantLayout>
        <Head title="My Profile" />
        <PageHeader title="Applicant profile" kicker="Keep this information current" />
        <form class="panel" @submit.prevent="submit">
            <div class="grid gap-4 p-3 md:grid-cols-2 sm:p-4">
                <div class="md:col-span-2"><label>Full name</label><input v-model="form.full_name" required></div>
                <div><label>Date of birth</label><input v-model="form.date_of_birth" type="date" required></div>
                <div>
                    <label>Sex</label>
                    <select v-model="form.sex" required>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div><label>Contact number</label><input v-model="form.contact_number" required></div>
                <div>
                    <label>Beneficiary type</label>
                    <select v-model="form.beneficiary_type">
                        <option value="student">Student</option>
                        <option value="non_student">Non-student</option>
                    </select>
                </div>
                <div class="md:col-span-2"><label>Street</label><input v-model="form.street" required></div>
                <div>
                    <label>Barangay</label>
                    <select v-model="form.barangay">
                        <option v-for="barangay in barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                    </select>
                </div>
                <div><label>Municipality / City</label><input v-model="form.municipality" required></div>
                <div><label>Province</label><input v-model="form.province" required></div>
                <div><label>School (students)</label><input v-model="form.school_name" :disabled="! isStudent"></div>
                <div><label>Course / program</label><input v-model="form.course_or_program" :disabled="! isStudent"></div>
                <div><label>Year / grade level</label><input v-model="form.year_level" :disabled="! isStudent"></div>
            </div>
            <div class="border-t border-gov-border p-4"><button class="btn-primary w-full sm:w-auto" type="submit" :disabled="form.processing">Save profile</button></div>
        </form>
    </ApplicantLayout>
</template>
