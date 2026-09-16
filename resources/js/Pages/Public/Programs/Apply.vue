<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Turnstile from '@/Components/Turnstile.vue';
import ProgramFormFields from '@/Components/ProgramFormFields.vue';
import { currentCsrfToken } from '@/bootstrap';
import { useNotify } from '@/composables/useNotify';

const props = defineProps({
    program: { type: Object, required: true },
    turnstilePassed: { type: Boolean, default: false },
});

const page = usePage();
const { notify } = useNotify();
const auth = computed(() => page.props.auth?.user);
const barangays = computed(() => page.props.gov?.barangays ?? []);
const pwdTypes = computed(() => page.props.gov?.pwd_types ?? {});

const step = ref(1);
const otpSent = ref(false);
const sendingOtp = ref(false);
const otpDigits = ref(['', '', '', '', '', '']);
const otpInputs = ref([]);

const OTP_LIFETIME_SECONDS = 10 * 60;
const otpExpiresAt = ref(null);
const timeRemaining = ref(0);
let otpTimer = null;

const tickTimer = () => {
    if (! otpExpiresAt.value) {
        timeRemaining.value = 0;
        return;
    }
    const remaining = Math.max(0, Math.round((otpExpiresAt.value - Date.now()) / 1000));
    timeRemaining.value = remaining;
    if (remaining <= 0) {
        stopTimer();
    }
};

const stopTimer = () => {
    if (otpTimer) {
        clearInterval(otpTimer);
        otpTimer = null;
    }
};

const startTimer = () => {
    stopTimer();
    otpExpiresAt.value = Date.now() + OTP_LIFETIME_SECONDS * 1000;
    tickTimer();
    otpTimer = setInterval(tickTimer, 1000);
};

const formattedTimeRemaining = computed(() => {
    const total = Math.max(0, timeRemaining.value);
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const canSendOtp = computed(() => ! sendingOtp.value && timeRemaining.value <= 0);

onBeforeUnmount(() => stopTimer());

const turnstileSiteKey = computed(() => page.props.turnstile?.site_key || '');
const turnstileEnabled = computed(() => page.props.turnstile?.enabled !== false);
const turnstileToken = ref('');
const turnstileVerified = ref(! turnstileEnabled.value);
const turnstileChecking = ref(false);
const turnstileRef = ref(null);

const resetTurnstileGate = () => {
    turnstileToken.value = '';
    turnstileVerified.value = false;
    turnstileChecking.value = false;
    turnstileRef.value?.reset();
};

const onTurnstileVerified = async (token) => {
    turnstileToken.value = token;
    turnstileChecking.value = true;

    try {
        const csrf = page.props.csrf_token || currentCsrfToken();
        await window.axios.post(
            route('site.apply.turnstile', props.program.slug, false),
            {
                turnstile_token: token,
                _token: csrf,
            },
            {
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            },
        );
        turnstileVerified.value = true;
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        const first = Object.values(errors).flat()[0] || error.response?.data?.message || 'The security check failed. Please try again.';
        notify.error(first);
        resetTurnstileGate();
    } finally {
        turnstileChecking.value = false;
    }
};

const onTurnstileExpired = () => {
    turnstileToken.value = '';
    turnstileVerified.value = false;
    turnstileChecking.value = false;
};

const PROFILE_FORM_KEYS = ['school_name', 'course_or_program', 'year_level'];
const STUDENT_FIELD_KEYS = [
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
];
const programFormFields = computed(() => props.program.form_fields || []);
const extraFormFields = computed(() => programFormFields.value.filter((field) => ! PROFILE_FORM_KEYS.includes(field.name)));
const initialBeneficiaryType = props.program.beneficiary_type === 'non_student' ? 'non_student' : 'student';

const initialAnswers = {};
(props.program.form_fields || []).forEach((field) => {
    initialAnswers[field.name] = '';
});

const form = useForm({
    eligibility_confirmed: false,
    turnstile_token: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    date_of_birth: '',
    sex: '',
    street: '',
    barangay: '',
    municipality: 'Nabua',
    province: 'Camarines Sur',
    contact_number: '',
    email: '',
    mother_name: '',
    mother_occupation: '',
    father_name: '',
    father_occupation: '',
    is_pwd: false,
    pwd_type: '',
    pwd_type_detail: '',
    beneficiary_type: initialBeneficiaryType,
    school_name: '',
    course_or_program: '',
    year_level: '',
    otp: '',
    answers: initialAnswers,
});

const isStudent = computed(() => form.beneficiary_type === 'student');
const disabledStudentFieldNames = computed(() => (isStudent.value ? [] : STUDENT_FIELD_KEYS));

const clearStudentFields = () => {
    form.school_name = '';
    form.course_or_program = '';
    form.year_level = '';
    PROFILE_FORM_KEYS.forEach((key) => form.clearErrors(key));
    programFormFields.value.forEach((field) => {
        if (STUDENT_FIELD_KEYS.includes(field.name)) {
            form.answers[field.name] = '';
            form.clearErrors(`answers.${field.name}`);
        }
    });
};

watch(() => form.beneficiary_type, (type) => {
    if (type === 'non_student') {
        clearStudentFields();
    }
});

const fullName = computed(() => [form.first_name, form.middle_name, form.last_name]
    .map((part) => String(part || '').trim())
    .filter((part) => part.length > 0)
    .join(' '));

onMounted(() => {
    if (auth.value) {
        const name = String(auth.value.name || '').trim();
        if (name) {
            const parts = name.split(/\s+/);
            if (parts.length === 1) {
                form.first_name = parts[0];
            } else if (parts.length === 2) {
                [form.first_name, form.last_name] = parts;
            } else {
                form.first_name = parts.shift();
                form.last_name = parts.pop();
                form.middle_name = parts.join(' ');
            }
        }
        form.email = auth.value.email || '';
    }
});

const goNext = () => {
    if (step.value === 1) {
        if (! form.eligibility_confirmed) {
            form.setError('eligibility_confirmed', 'You must confirm that you meet the eligibility conditions before continuing.');
            return;
        }

        form.clearErrors();
        step.value = 2;
        return;
    }

    if (step.value === 2 && ! validateStepOne()) {
        return;
    }

    form.clearErrors();
    step.value = 3;
};

const goBack = () => {
    if (step.value > 1) {
        step.value -= 1;
    }
};

const syncProfileAnswers = () => {
    programFormFields.value.forEach((field) => {
        if (PROFILE_FORM_KEYS.includes(field.name)) {
            form.answers[field.name] = isStudent.value ? form[field.name] : '';
        }
    });
};

const validateStepOne = () => {
    syncProfileAnswers();

    const required = [
        'first_name', 'last_name', 'date_of_birth', 'sex', 'street', 'barangay', 'municipality', 'province',
        'contact_number', 'email', 'mother_name', 'mother_occupation', 'father_name', 'father_occupation',
    ];

    if (isStudent.value) {
        required.push('school_name', 'course_or_program', 'year_level');
    }

    let ok = true;
    required.forEach((field) => {
        if (! String(form[field] || '').trim()) {
            form.setError(field, 'This field is required.');
            ok = false;
        }
    });

    extraFormFields.value.forEach((field) => {
        if (! isStudent.value && STUDENT_FIELD_KEYS.includes(field.name)) {
            return;
        }
        if (field.is_required && ! String(form.answers[field.name] ?? '').trim()) {
            form.setError(`answers.${field.name}`, 'This field is required.');
            ok = false;
        }
    });

    if (form.is_pwd && ! form.pwd_type) {
        form.setError('pwd_type', 'Please specify the type of disability.');
        ok = false;
    }

    if (form.is_pwd && form.pwd_type === 'other' && ! String(form.pwd_type_detail || '').trim()) {
        form.setError('pwd_type_detail', 'Please specify the type of disability.');
        ok = false;
    }

    return ok;
};

const sendOtp = async () => {
    if (! form.email || ! fullName.value) {
        form.setError('email', 'Name and email are required before sending a code.');
        return;
    }

    if (! turnstileVerified.value || ! turnstileToken.value) {
        notify.error('Please complete the security check before requesting a code.');
        turnstileVerified.value = false;
        turnstileToken.value = '';
        return;
    }

    sendingOtp.value = true;
    try {
        const token = page.props.csrf_token || currentCsrfToken();
        const { data } = await window.axios.post(
            route('site.apply.otp', props.program.slug, false),
            {
                email: form.email,
                full_name: fullName.value,
                turnstile_token: turnstileToken.value,
                _token: token,
            },
            {
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
            },
        );
        otpSent.value = true;
        startTimer();
        notify.success(data.message || 'Verification code sent. It will expire in 10 minutes.');
    } catch (error) {
        if (error.response?.status === 419) {
            notify.error('Your session expired. Refresh this page, then send the code again.');
            return;
        }

        const errors = error.response?.data?.errors || {};
        const first = Object.values(errors).flat()[0] || error.response?.data?.message || 'Could not send the verification code.';
        notify.error(first);
        if (errors.email) {
            form.setError('email', errors.email[0]);
        }
        if (errors.turnstile_token) {
            turnstileToken.value = '';
            turnstileVerified.value = false;
            turnstileRef.value?.reset();
        }
    } finally {
        sendingOtp.value = false;
    }
};

const setOtpDigit = (index, rawValue) => {
    const digit = String(rawValue || '').replace(/\D/g, '').slice(0, 1);
    otpDigits.value[index] = digit;
    form.otp = otpDigits.value.join('');
    if (digit && index < 5) {
        nextTick(() => otpInputs.value[index + 1]?.focus());
    }
};

const handleOtpInput = (index, event) => {
    setOtpDigit(index, event.target.value);
    event.target.value = otpDigits.value[index] || '';
};

const handleOtpKeydown = (index, event) => {
    if (event.key === 'Backspace' && ! otpDigits.value[index] && index > 0) {
        event.preventDefault();
        otpInputs.value[index - 1]?.focus();
        otpDigits.value[index - 1] = '';
        form.otp = otpDigits.value.join('');
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        otpInputs.value[index - 1]?.focus();
    } else if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        otpInputs.value[index + 1]?.focus();
    }
};

const handleOtpPaste = (event) => {
    const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (! pasted) {
        return;
    }
    event.preventDefault();
    for (let i = 0; i < 6; i++) {
        otpDigits.value[i] = pasted[i] || '';
    }
    form.otp = otpDigits.value.join('');
    const nextIndex = Math.min(5, pasted.length - 1);
    nextTick(() => otpInputs.value[nextIndex]?.focus());
};

watch(() => step.value, (value) => {
    if (value === 3) {
        nextTick(() => otpInputs.value[0]?.focus());
    }
});

const submit = () => {
    syncProfileAnswers();
    form.otp = otpDigits.value.join('');
    form.turnstile_token = turnstileToken.value;
    form.transform((data) => ({
        ...data,
        full_name: fullName.value,
        turnstile_token: turnstileToken.value,
        eligibility_confirmed: data.eligibility_confirmed ? 1 : 0,
        is_pwd: data.is_pwd ? 1 : 0,
        pwd_type: data.is_pwd ? data.pwd_type : '',
        pwd_type_detail: data.is_pwd && data.pwd_type === 'other' ? data.pwd_type_detail : '',
        answers: data.answers || {},
    })).post(route('site.apply.store', props.program.slug, false), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => stopTimer(),
        onError: (errors) => {
            const keys = Object.keys(errors);
            if (keys.some((key) => key === 'eligibility_confirmed')) {
                step.value = 1;
            } else if (keys.some((key) => key !== 'otp' && key !== 'turnstile_token')) {
                step.value = 2;
            }
            if (errors.turnstile_token) {
                notify.error(errors.turnstile_token);
                turnstileToken.value = '';
                turnstileVerified.value = false;
                turnstileRef.value?.reset();
            }
        },
    });
};
</script>

<template>
    <PublicLayout>
        <Head :title="'Apply — ' + program.name" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">{{ program.code }} · Step {{ step }} of 3</p>
                <h1 class="mt-1">Apply for {{ program.name }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#d7e6f7]">Confirm eligibility, fill in the application form, then verify your email. We will email you a sign-in link and password so you can continue with the requirements and review inside your applicant portal.</p>
            </div>
        </div>
        <div class="mx-auto max-w-4xl px-4 py-6 md:py-8">
            <div v-if="!turnstileVerified" class="panel">
                <div class="panel-h">Security check</div>
                <div class="panel-body space-y-4 text-center">
                    <p class="text-sm text-gov-text">
                        Please complete the verification below before you can start your application for <strong>{{ program.name }}</strong>.
                    </p>
                    <p class="text-xs text-gov-muted">
                        We use Cloudflare Turnstile to make sure you are a real person and to protect the office from automated submissions.
                    </p>
                    <div v-if="turnstileEnabled" class="flex min-h-[90px] justify-center overflow-visible py-2">
                        <Turnstile
                            v-if="turnstileSiteKey"
                            ref="turnstileRef"
                            :site-key="turnstileSiteKey"
                            size="flexible"
                            @verified="onTurnstileVerified"
                            @expired="onTurnstileExpired"
                        />
                        <p v-else class="text-sm text-gov-danger">
                            Cloudflare is not configured. Please contact the office.
                        </p>
                    </div>
                    <p v-if="turnstileChecking" class="text-sm font-medium text-gov-dark">Confirming with Cloudflare…</p>
                </div>
            </div>

            <div v-else class="panel">
                <ol class="grid grid-cols-3 border-b border-gov-border text-center text-[11px] font-bold uppercase tracking-wide">
                    <li class="px-2 py-3" :class="step === 1 ? 'bg-gov-light text-gov-blue' : 'text-gov-muted'">1. Eligibility</li>
                    <li class="px-2 py-3" :class="step === 2 ? 'bg-gov-light text-gov-blue' : 'text-gov-muted'">2. Application form</li>
                    <li class="px-2 py-3" :class="step === 3 ? 'bg-gov-light text-gov-blue' : 'text-gov-muted'">3. Email verification</li>
                </ol>

                <div class="p-4 md:p-6">
                    <div v-if="step === 1" class="space-y-4">
                        <h3 class="form-section-title">Eligibility conditions</h3>
                        <p class="text-sm">{{ program.eligibility }}</p>
                        <ul class="list-disc pl-5 text-sm">
                            <li v-for="rule in program.eligibility_rules" :key="rule.id">{{ rule.label }}</li>
                        </ul>
                        <label class="flex items-start gap-2 text-sm font-normal normal-case tracking-normal">
                            <input v-model="form.eligibility_confirmed" type="checkbox" class="mt-1 h-4 w-4">
                            I confirm that I meet the eligibility conditions of this program and that the information I will provide is true and complete.
                        </label>
                        <p v-if="form.errors.eligibility_confirmed" class="field-error">{{ form.errors.eligibility_confirmed }}</p>
                    </div>

                    <div v-else-if="step === 2" class="space-y-6">
                        <section>
                            <h3 class="form-section-title">Personal information</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label>First name</label>
                                    <input v-model="form.first_name" type="text" autocomplete="given-name">
                                    <p v-if="form.errors.first_name || form.errors.full_name" class="field-error">{{ form.errors.first_name || form.errors.full_name }}</p>
                                </div>
                                <div>
                                    <label>Middle name <span class="text-gov-muted">(optional)</span></label>
                                    <input v-model="form.middle_name" type="text" autocomplete="additional-name">
                                    <p v-if="form.errors.middle_name" class="field-error">{{ form.errors.middle_name }}</p>
                                </div>
                                <div>
                                    <label>Last name</label>
                                    <input v-model="form.last_name" type="text" autocomplete="family-name">
                                    <p v-if="form.errors.last_name" class="field-error">{{ form.errors.last_name }}</p>
                                </div>
                                <div>
                                    <label>Date of birth</label>
                                    <input v-model="form.date_of_birth" type="date">
                                    <p v-if="form.errors.date_of_birth" class="field-error">{{ form.errors.date_of_birth }}</p>
                                </div>
                                <div>
                                    <label>Sex</label>
                                    <select v-model="form.sex">
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    <p v-if="form.errors.sex" class="field-error">{{ form.errors.sex }}</p>
                                </div>
                                <div>
                                    <label>Contact number</label>
                                    <input v-model="form.contact_number" type="tel">
                                    <p v-if="form.errors.contact_number" class="field-error">{{ form.errors.contact_number }}</p>
                                </div>
                                <div>
                                    <label>Email</label>
                                    <input v-model="form.email" type="email">
                                    <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="form-section-title">Address</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label>Address (house no. / street)</label>
                                    <input v-model="form.street" type="text">
                                    <p v-if="form.errors.street" class="field-error">{{ form.errors.street }}</p>
                                </div>
                                <div>
                                    <label>Barangay</label>
                                    <select v-model="form.barangay">
                                        <option value="">Select</option>
                                        <option v-for="barangay in barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
                                    </select>
                                    <p v-if="form.errors.barangay" class="field-error">{{ form.errors.barangay }}</p>
                                </div>
                                <div>
                                    <label>Municipality</label>
                                    <input v-model="form.municipality" type="text">
                                </div>
                                <div>
                                    <label>Province</label>
                                    <input v-model="form.province" type="text">
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="form-section-title">Parents / guardians</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label>Mother's name</label>
                                    <input v-model="form.mother_name" type="text">
                                    <p v-if="form.errors.mother_name" class="field-error">{{ form.errors.mother_name }}</p>
                                </div>
                                <div>
                                    <label>Mother's occupation</label>
                                    <input v-model="form.mother_occupation" type="text">
                                    <p v-if="form.errors.mother_occupation" class="field-error">{{ form.errors.mother_occupation }}</p>
                                </div>
                                <div>
                                    <label>Father's name</label>
                                    <input v-model="form.father_name" type="text">
                                    <p v-if="form.errors.father_name" class="field-error">{{ form.errors.father_name }}</p>
                                </div>
                                <div>
                                    <label>Father's occupation</label>
                                    <input v-model="form.father_occupation" type="text">
                                    <p v-if="form.errors.father_occupation" class="field-error">{{ form.errors.father_occupation }}</p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="form-section-title">Disability</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label>Person with disability (PWD)</label>
                                    <select v-model="form.is_pwd">
                                        <option :value="false">No</option>
                                        <option :value="true">Yes</option>
                                    </select>
                                </div>
                                <div v-if="form.is_pwd">
                                    <label>Type of disability</label>
                                    <select v-model="form.pwd_type">
                                        <option value="">Select type</option>
                                        <option v-for="(label, value) in pwdTypes" :key="value" :value="value">{{ label }}</option>
                                    </select>
                                    <p v-if="form.errors.pwd_type" class="field-error">{{ form.errors.pwd_type }}</p>
                                </div>
                                <div v-if="form.is_pwd && form.pwd_type === 'other'" class="sm:col-span-2">
                                    <label>Please specify</label>
                                    <input v-model="form.pwd_type_detail" type="text" placeholder="Type of disability">
                                    <p v-if="form.errors.pwd_type_detail" class="field-error">{{ form.errors.pwd_type_detail }}</p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="form-section-title">Education</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label>Beneficiary type</label>
                                    <select v-model="form.beneficiary_type">
                                        <option value="student" :disabled="program.beneficiary_type === 'non_student'">Student</option>
                                        <option value="non_student" :disabled="program.beneficiary_type === 'student'">Non-student</option>
                                    </select>
                                    <p v-if="form.errors.beneficiary_type" class="field-error">{{ form.errors.beneficiary_type }}</p>
                                </div>
                                <div>
                                    <label>Name of school</label>
                                    <input v-model="form.school_name" type="text" :disabled="! isStudent">
                                    <p v-if="form.errors.school_name" class="field-error">{{ form.errors.school_name }}</p>
                                </div>
                                <div>
                                    <label>Course / program</label>
                                    <input v-model="form.course_or_program" type="text" placeholder="e.g. General Academic Strand" :disabled="! isStudent">
                                    <p v-if="form.errors.course_or_program" class="field-error">{{ form.errors.course_or_program }}</p>
                                </div>
                                <div>
                                    <label>Year level</label>
                                    <input v-model="form.year_level" type="text" placeholder="e.g. Grade 12 / 1st Year College" :disabled="! isStudent">
                                    <p v-if="form.errors.year_level" class="field-error">{{ form.errors.year_level }}</p>
                                </div>
                            </div>
                        </section>

                        <section v-if="extraFormFields.length">
                            <h3 class="form-section-title">Program information</h3>
                            <p class="mb-3 text-sm text-gov-muted">These questions were added for this assistance program and will be used in review, evaluation, and document matching.</p>
                            <ProgramFormFields
                                :fields="extraFormFields"
                                :model="form.answers"
                                :errors="form.errors"
                                error-prefix="answers."
                                :disabled-names="disabledStudentFieldNames"
                            />
                        </section>
                    </div>

                    <div v-else-if="step === 3" class="space-y-4">
                        <div class="space-y-1 text-center">
                            <h3 class="form-section-title !mb-1">Email verification</h3>
                            <p class="text-sm text-gov-text">We will email a 6-digit verification code to</p>
                            <p class="text-base font-bold text-gov-dark">{{ form.email }}</p>
                            <p class="text-xs text-gov-muted">Enter the code below to confirm your application. The code will expire after 10 minutes.</p>
                        </div>

                        <div class="flex justify-center">
                            <button
                                class="btn-secondary btn-sm"
                                type="button"
                                :disabled="! canSendOtp"
                                @click="sendOtp"
                            >
                                <template v-if="sendingOtp">Sending…</template>
                                <template v-else-if="timeRemaining > 0">Resend in {{ formattedTimeRemaining }}</template>
                                <template v-else-if="otpSent">Resend verification code</template>
                                <template v-else>Send verification code</template>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-center text-xs font-bold uppercase tracking-wide text-gov-muted">One-time code</label>
                            <div class="flex justify-center gap-2 sm:gap-3">
                                <input
                                    v-for="index in 6"
                                    :key="index"
                                    :ref="(el) => (otpInputs[index - 1] = el)"
                                    :value="otpDigits[index - 1]"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="1"
                                    class="h-12 w-10 border border-gov-border bg-white text-center text-xl font-bold text-gov-dark shadow-sm transition-colors focus:border-gov-blue focus:outline-none focus:ring-2 focus:ring-gov-blue/40 disabled:cursor-not-allowed disabled:bg-gov-off disabled:text-gov-muted sm:h-14 sm:w-12 sm:text-2xl"
                                    style="border-radius: 0"
                                    :disabled="! otpSent"
                                    @input="handleOtpInput(index - 1, $event)"
                                    @keydown="handleOtpKeydown(index - 1, $event)"
                                    @paste="handleOtpPaste"
                                >
                            </div>
                            <p v-if="form.errors.otp" class="field-error text-center">{{ form.errors.otp }}</p>
                            <p v-if="otpSent && timeRemaining > 0" class="text-center text-xs text-gov-muted">
                                Code expires in <strong class="text-gov-dark">{{ formattedTimeRemaining }}</strong>.
                            </p>
                            <p v-else-if="otpSent && timeRemaining === 0" class="text-center text-xs text-gov-danger">
                                Your code has expired. Please request a new one.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-between gap-2 border-t border-gov-border bg-gov-off px-4 py-3">
                    <Link v-if="step === 1" class="btn-ghost btn-sm" :href="route('site.programs.show', program.slug)">Cancel</Link>
                    <button v-else class="btn-ghost btn-sm" type="button" :disabled="form.processing" @click="goBack">Back</button>
                    <button v-if="step === 1" class="btn-primary btn-sm" type="button" :disabled="!form.eligibility_confirmed" @click="goNext">Continue to application form</button>
                    <button v-else-if="step === 2" class="btn-primary btn-sm" type="button" @click="goNext">Next</button>
                    <button v-else class="btn-primary btn-sm" type="button" :disabled="form.processing || !otpSent || otpDigits.join('').length !== 6 || timeRemaining === 0" @click="submit">
                        {{ form.processing ? 'Submitting…' : 'Verify and submit' }}
                    </button>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
