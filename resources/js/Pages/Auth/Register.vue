<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PasswordInput from '@/Components/PasswordInput.vue';

const props = defineProps({
    barangays: { type: Array, default: () => [] },
});

const form = useForm({
    full_name: '',
    date_of_birth: '',
    sex: '',
    street: '',
    barangay: '',
    municipality: 'Nabua',
    province: 'Camarines Sur',
    contact_number: '',
    email: '',
    beneficiary_type: 'non_student',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(route('register.store'));
</script>

<template>
    <AuthLayout wide>
        <Head title="Applicant Registration" />
        <div class="panel">
            <div class="panel-h">Applicant Registration</div>
            <div class="panel-body">
                <p class="mb-4 text-sm text-gov-muted">Create an account to apply for available cash assistance programs. Provide accurate information. False statements may result in disqualification.</p>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div>
                        <label for="full_name">Full name</label>
                        <input id="full_name" v-model="form.full_name" type="text" autocomplete="name" required>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" v-model="form.date_of_birth" type="date" required>
                        </div>
                        <div>
                            <label for="sex">Sex</label>
                            <select id="sex" v-model="form.sex" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="street">Address (house no. / street)</label>
                        <input id="street" v-model="form.street" type="text" autocomplete="street-address" required>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label for="barangay">Barangay</label>
                            <select id="barangay" v-model="form.barangay" required>
                                <option value="">Select</option>
                                <option v-for="barangay in props.barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="municipality">Municipality / City</label>
                            <input id="municipality" v-model="form.municipality" type="text" required>
                        </div>
                        <div>
                            <label for="province">Province</label>
                            <input id="province" v-model="form.province" type="text" required>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact_number">Contact number</label>
                            <input id="contact_number" v-model="form.contact_number" type="tel" autocomplete="tel" required>
                        </div>
                        <div>
                            <label for="email">Email address</label>
                            <input id="email" v-model="form.email" type="email" required>
                        </div>
                    </div>
                    <div>
                        <label for="beneficiary_type">Beneficiary type</label>
                        <select id="beneficiary_type" v-model="form.beneficiary_type" required>
                            <option value="student">Student</option>
                            <option value="non_student">Non-student</option>
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password">Password</label>
                            <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required />
                        </div>
                        <div>
                            <label for="password_confirmation">Confirm password</label>
                            <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
                        </div>
                    </div>
                    <button class="btn-primary w-full" type="submit" :disabled="form.processing">Create applicant account</button>
                </form>
                <p class="mt-4 text-sm">Already registered? <Link :href="route('login')">Sign in</Link></p>
            </div>
        </div>
    </AuthLayout>
</template>
