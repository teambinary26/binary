<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ApplySteps from '@/Components/ApplySteps.vue';

const props = defineProps({
    application: { type: Object, required: true },
});

const documentFor = (requirementId) => props.application.documents.find((d) => d.requirement_id === requirementId);
const form = useForm({});
const submit = () => form.post(route('applicant.apply.submit', props.application.id));
const hasFormFields = computed(() => (props.application.program_detail?.form_fields || []).length > 0);
const reviewKicker = computed(() => hasFormFields.value ? 'Step 3 — Review and submit' : 'Step 2 — Review and submit');
</script>

<template>
    <ApplicantLayout>
        <Head title="Review Application" />
        <PageHeader :title="application.program?.name" :kicker="reviewKicker" :document-no="application.application_no" />
        <ApplySteps :application="application" />
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-h">Applicant</div>
                <dl class="divide-y divide-gov-border p-0 text-sm">
                    <div class="break-words px-4 py-2">{{ application.applicant_detail?.full_name }}</div>
                    <div class="break-words px-4 py-2">{{ application.applicant_detail?.full_address }}</div>
                    <div class="break-all px-4 py-2">{{ application.applicant_detail?.contact_number }} · {{ application.applicant_detail?.email }}</div>
                </dl>
            </div>
            <div class="panel">
                <div class="panel-h">Program</div>
                <div class="panel-body text-sm">
                    <p class="break-words font-bold">{{ application.program?.name }}</p>
                    <p>{{ application.program?.amount_display }}</p>
                </div>
            </div>
        </div>
        <div class="panel mt-4">
            <div class="panel-h">Submitted information</div>
            <dl class="divide-y divide-gov-border text-sm">
                <div v-for="answer in application.answers" :key="answer.field_name" class="grid grid-cols-1 gap-1 px-4 py-2 sm:grid-cols-3 sm:gap-0">
                    <dt class="font-semibold break-words">{{ answer.field_label }}</dt>
                    <dd class="break-words sm:col-span-2">{{ answer.value || '—' }}</dd>
                </div>
            </dl>
        </div>
        <div class="panel mt-4">
            <div class="panel-h">Requirements</div>
            <ul class="divide-y divide-gov-border text-sm">
                <li v-for="requirement in application.program_detail?.requirements" :key="requirement.id" class="flex flex-col gap-1 px-4 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <span class="min-w-0 break-words font-semibold sm:font-normal">{{ requirement.name }}</span>
                    <span class="min-w-0 break-all text-sm sm:max-w-[55%] sm:text-right" :class="['revision_requested', 'rejected'].includes(documentFor(requirement.id)?.status) ? 'text-gov-warning' : 'text-gov-muted sm:text-gov-text'">
                        {{ documentFor(requirement.id)?.status_label && ['revision_requested', 'rejected'].includes(documentFor(requirement.id)?.status) ? documentFor(requirement.id).status_label : (documentFor(requirement.id)?.original_name || 'Not uploaded') }}
                    </span>
                </li>
            </ul>
        </div>
        <div v-if="application.needs_document_action" class="panel mt-4 border-l-4 border-l-gov-warning">
            <div class="panel-body text-sm">
                <p>The office still needs a replacement for one or more documents. Replace those files so the application can return to verification.</p>
                <Link class="btn-primary btn-sm mt-3 inline-flex" :href="route('applicant.apply.documents', application.id)">Replace documents</Link>
            </div>
        </div>
        <form v-else class="mt-4" @submit.prevent="submit">
            <p v-if="form.errors.form" class="mb-3 field-error">{{ form.errors.form }}</p>
            <p class="mb-3 break-words text-sm">By submitting, you certify that the information and documents are true. Providing false information may result in denial of assistance and administrative action.</p>
            <button class="btn-primary w-full sm:w-auto" type="submit" :disabled="form.processing">Submit application</button>
        </form>
    </ApplicantLayout>
</template>
