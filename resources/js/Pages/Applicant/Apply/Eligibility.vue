<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    application: { type: Object, required: true },
});

const form = useForm({ confirm: false });
const submit = () => form.post(route('applicant.apply.eligibility.store', props.application.id));
</script>

<template>
    <ApplicantLayout>
        <Head title="Eligibility" />
        <PageHeader :title="application.program?.name" kicker="Step 1 — Check eligibility" :document-no="application.application_no" />
        <div class="panel">
            <div class="panel-h">Eligibility conditions</div>
            <div class="panel-body">
                <p class="mb-3 break-words text-sm">{{ application.program_detail?.eligibility }}</p>
                <ul class="mb-4 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="rule in application.program_detail?.eligibility_rules" :key="rule.id" class="break-words">{{ rule.label }}</li>
                </ul>
                <form @submit.prevent="submit">
                    <label class="flex items-start gap-2 text-sm font-normal normal-case leading-snug tracking-normal">
                        <input v-model="form.confirm" type="checkbox" value="1" class="mt-1 h-4 w-4 shrink-0" required>
                        I confirm that I meet the eligibility conditions of this program and that the information I will provide is true and complete.
                    </label>
                    <p v-if="form.errors.confirm" class="field-error">{{ form.errors.confirm }}</p>
                    <button class="btn-primary mt-4 w-full sm:w-auto" type="submit" :disabled="form.processing">Continue</button>
                </form>
            </div>
        </div>
    </ApplicantLayout>
</template>
