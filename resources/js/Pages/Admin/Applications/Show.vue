<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Timeline from '@/Components/Timeline.vue';
import FileViewerModal from '@/Components/FileViewerModal.vue';
import OcrReviewModal from '@/Components/OcrReviewModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useCan } from '@/composables/useCan';
import { useNotify } from '@/composables/useNotify';

const { can } = useCan();
const { notify } = useNotify();

const props = defineProps({
    application: { type: Object, required: true },
    staff: { type: Array, default: () => [] },
    workflowSteps: { type: Array, default: () => [] },
    assignedQueue: { type: Object, default: () => ({}) },
    abilities: { type: Object, default: () => ({}) },
});

const remarks = reactive({});
props.application.documents.forEach((document) => {
    remarks[document.id] = '';
});

const viewerDocument = ref(null);
const ocrDocumentId = ref(null);
const ocrDocument = computed(() =>
    props.application.documents.find((document) => document.id === ocrDocumentId.value) ?? null
);
const openViewer = (document, ruleId = null) => {
    if (! document) {
        return;
    }
    viewerDocument.value = document;
    if (ruleId) {
        viewedManualRules[ruleId] = true;
    }
};
const closeViewer = () => {
    viewerDocument.value = null;
};

const viewedManualRules = reactive({});
const firstSubmittedDocument = computed(() => props.application.documents?.[0] ?? null);

const requirementRows = computed(() => {
    const requirements = props.application.program_detail?.requirements || [];
    const documents = props.application.documents || [];
    const used = new Set();

    const rows = requirements.map((requirement) => {
        const document = documents.find((item) => item.requirement_id === requirement.id)
            || documents.find((item) => item.requirement_name === requirement.name)
            || null;

        if (document) {
            used.add(document.id);
        }

        return {
            key: `req-${requirement.id}`,
            required_name: requirement.name,
            is_required: Boolean(requirement.is_required),
            document,
        };
    });

    documents.filter((document) => ! used.has(document.id)).forEach((document) => {
        rows.push({
            key: `extra-${document.id}`,
            required_name: 'Not required',
            is_required: false,
            document,
        });
    });

    return rows;
});

const openManualDocuments = (rule) => {
    if (! firstSubmittedDocument.value) {
        notify.warning('No documents were submitted.');
        return;
    }

    openViewer(firstSubmittedDocument.value, rule.id);
};

const canMarkRule = (rule) => {
    if (! canDecideRules.value) {
        return false;
    }

    if (rule.check_mode !== 'manual') {
        return true;
    }

    return Boolean(viewedManualRules[rule.id] || ruleDecisions[rule.id] || ! firstSubmittedDocument.value);
};

const eligibilityCheck = computed(() => props.application.eligibility_assessment || null);
const ruleDecisions = reactive({});

const applyRuleDefaults = (preserveManual = true) => {
    const saved = props.application.latest_evaluation?.eligibility_checks || [];

    (eligibilityCheck.value?.rules || []).forEach((rule) => {
        const savedRule = saved.find((item) => item.id === rule.id);
        if (savedRule && (savedRule.status === 'passed' || savedRule.status === 'failed')) {
            ruleDecisions[rule.id] = savedRule.status;
            return;
        }

        if (preserveManual && rule.check_mode === 'manual' && ruleDecisions[rule.id]) {
            return;
        }

        if (rule.check_mode === 'ocr' && (rule.status === 'passed' || rule.status === 'failed')) {
            ruleDecisions[rule.id] = rule.status;
            return;
        }

        if (! ruleDecisions[rule.id]) {
            ruleDecisions[rule.id] = '';
        }
    });
};

applyRuleDefaults(false);

const ruleOutcome = (rule) => ruleDecisions[rule.id] || 'review';
const canDecideRules = computed(() => props.abilities.evaluate && props.application.is_in_evaluation);
const pendingRules = computed(() =>
    (eligibilityCheck.value?.rules || []).filter((rule) => !['passed', 'failed'].includes(ruleDecisions[rule.id]))
);

const combinedEligibility = computed(() => {
    const rules = eligibilityCheck.value?.rules || [];
    if (!rules.length) {
        return eligibilityCheck.value;
    }

    const passed = rules.filter((rule) => ruleDecisions[rule.id] === 'passed').length;
    const failed = rules.filter((rule) => ruleDecisions[rule.id] === 'failed').length;
    const review = rules.length - passed - failed;
    const status = failed > 0 ? 'not_eligible' : (review > 0 ? 'review' : 'eligible');

    return {
        ...eligibilityCheck.value,
        eligible: status === 'eligible' ? true : (status === 'not_eligible' ? false : null),
        status,
        status_label: status === 'eligible' ? 'Eligible' : (status === 'not_eligible' ? 'Not eligible' : 'Needs review'),
        tone: status === 'eligible' ? 'success' : (status === 'not_eligible' ? 'danger' : 'warning'),
        summary: failed > 0
            ? `${failed} program ${failed === 1 ? 'rule' : 'rules'} marked not met.`
            : (review > 0
                ? `${passed} of ${rules.length} rules are met. ${review} ${review === 1 ? 'rule still needs' : 'rules still need'} a staff decision.`
                : 'All program eligibility rules are met.'),
        passed_count: passed,
        failed_count: failed,
        review_count: review,
    };
});

const syncEligibilityForm = () => {
    const result = combinedEligibility.value;
    evaluate.eligibility_passed = result?.eligible === true;
    evaluate.eligibility_checks = (eligibilityCheck.value?.rules || []).map((rule) => ({
        id: rule.id,
        label: rule.label,
        check_mode: rule.check_mode,
        status: ruleDecisions[rule.id] || 'review',
    }));
    if (!props.application.latest_evaluation?.assessment) {
        evaluate.assessment = result?.summary || '';
    }
};

const decideRule = (ruleId, status) => {
    ruleDecisions[ruleId] = status;
    syncEligibilityForm();
};

const evaluate = useForm({
    eligibility_passed: props.application.latest_evaluation?.eligibility_passed
        ?? props.application.eligibility_assessment?.eligible === true,
    eligibility_checks: props.application.latest_evaluation?.eligibility_checks || [],
    documents_complete: props.application.latest_evaluation?.documents_complete
        ?? Boolean(props.application.required_documents_verified),
    assessment: props.application.latest_evaluation?.assessment
        || props.application.eligibility_assessment?.summary
        || '',
    recommendation: props.application.latest_evaluation?.recommendation ?? 'approval',
    recommended_amount: props.application.latest_evaluation?.recommended_amount ?? props.application.program?.amount ?? '',
    remarks: props.application.latest_evaluation?.remarks ?? '',
});

watch(() => props.application.eligibility_assessment, () => {
    applyRuleDefaults(true);
    syncEligibilityForm();
}, { deep: true });

syncEligibilityForm();

const scanEligibility = useForm({});
const runEligibilityScan = () => {
    scanEligibility.post(route('admin.applications.scan-eligibility', props.application.id), {
        preserveScroll: true,
    });
};

const evaluationConfirmMessage = computed(() => {
    const number = props.application.application_no;
    const check = combinedEligibility.value;
    const resultLine = check ? `\n\nEligibility: ${check.status_label}. ${check.summary}` : '';
    const warning = evaluate.recommendation === 'approval' && check?.eligible === false
        ? '\n\nOne or more eligibility rules were marked not met. Confirm you still want to recommend approval.'
        : '';

    if (evaluate.recommendation === 'revision') {
        return `Save this evaluation and return application ${number} to the applicant for revision?${resultLine}`;
    }

    return `Save this evaluation and send application ${number} to approval? Assigned approval staff will take over this record.${resultLine}${warning}`;
});

const decide = useForm({
    decision: 'approved',
    approved_amount: props.application.latest_evaluation?.recommended_amount ?? props.application.program?.amount ?? '',
    remarks: '',
});

const initialReview = useForm({ remarks: '' });
const initialAction = ref(null); // 'approve' | 'reject' | null

const requestApprove = () => {
    initialAction.value = 'approve';
};

const requestReject = () => {
    if (! initialReview.remarks?.trim()) {
        initialReview.setError('remarks', 'Please provide a reason for rejecting the application.');
        return;
    }
    initialAction.value = 'reject';
};

const cancelInitialAction = () => {
    if (initialReview.processing) return;
    initialAction.value = null;
};

const confirmInitialAction = () => {
    const routeName = initialAction.value === 'approve'
        ? 'admin.applications.approve-applicant'
        : 'admin.applications.reject-applicant';

    initialReview.post(route(routeName, props.application.id), {
        preserveScroll: true,
        onFinish: () => {
            initialAction.value = null;
        },
    });
};

const completeVerification = useForm({});
const workflowAction = ref(null);

const requestCompleteVerification = () => {
    workflowAction.value = 'complete-verification';
};

const requestSaveEvaluate = () => {
    syncEligibilityForm();

    const unreadManual = (eligibilityCheck.value?.rules || []).filter((rule) => (
        rule.check_mode === 'manual'
        && firstSubmittedDocument.value
        && ! viewedManualRules[rule.id]
        && ! ruleDecisions[rule.id]
    ));

    if (unreadManual.length) {
        notify.warning('View the submitted documents before marking a manual rule.');
        return;
    }

    if (pendingRules.value.length) {
        notify.warning('Mark every rule as Met or Not met before saving.');
        return;
    }

    workflowAction.value = 'submit-evaluation';
};

const cancelWorkflowAction = () => {
    if (completeVerification.processing || evaluate.processing) return;
    workflowAction.value = null;
};

const confirmWorkflowAction = () => {
    if (workflowAction.value === 'complete-verification') {
        completeVerification.post(route('admin.applications.complete-verification', props.application.id), {
            preserveScroll: true,
            onFinish: () => {
                workflowAction.value = null;
            },
        });
        return;
    }

    evaluate.post(route('admin.applications.evaluate', props.application.id), {
        preserveScroll: true,
        onFinish: () => {
            workflowAction.value = null;
        },
    });
};
const saveDecision = (decision) => {
    decide.decision = decision;
    decide.post(route('admin.applications.decide', props.application.id), { preserveScroll: true });
};
const runOcr = (documentId) => {
    useForm({}).post(route('admin.applications.documents.ocr', [props.application.id, documentId]), {
        preserveScroll: true,
    });
};

const awaitingRevision = (document) => document?.status === 'revision_requested';

const verifyDocument = (documentId, action) => {
    const document = props.application.documents.find((item) => item.id === documentId);

    if (action === 'verify' && awaitingRevision(document)) {
        notify.warning('Wait for the applicant to replace this document before verifying it.');
        return;
    }

    const note = (remarks[documentId] || '').trim();

    if ((action === 'revision' || action === 'reject') && ! note) {
        notify.warning(
            action === 'revision'
                ? 'Please enter remarks before requesting revision.'
                : 'Please enter remarks before rejecting this document.',
        );
        return;
    }

    useForm({ action, remarks: note }).post(
        route('admin.applications.documents.verify', [props.application.id, documentId]),
        { preserveScroll: true },
    );
};

const queueHref = (applicationId) => route('admin.applications.show', {
    application: applicationId,
    ...(props.assignedQueue.step ? { step: props.assignedQueue.step } : {}),
});

const stepStateClass = (state) => ({
    done: 'bg-green-600 text-white border-green-600',
    current: 'bg-gov-blue text-white border-gov-blue ring-2 ring-gov-blue/30',
    upcoming: 'bg-gray-100 text-gray-400 border-gray-200',
}[state] || 'bg-gray-100 text-gray-400 border-gray-200');

const stepConnectorClass = (state) => ({
    done: 'bg-green-600',
    current: 'bg-gov-blue',
    upcoming: 'bg-gray-200',
}[state] || 'bg-gray-200');

const showDetails = ref(false);
</script>

<template>
    <AdminLayout>
        <Head :title="application.application_no" />
        <PageHeader :title="application.application_no" kicker="Application record" :document-no="application.program?.name">
            <template #actions>
                <Link class="btn-ghost btn-sm" :href="route('admin.applications.index')">
                    &larr; Back to applications
                </Link>
                <Link
                    v-if="assignedQueue.previous"
                    class="btn-ghost btn-sm"
                    :href="queueHref(assignedQueue.previous.id)"
                    :title="assignedQueue.previous.application_no"
                >
                    &larr; Previous
                </Link>
                <Link
                    v-if="assignedQueue.next"
                    class="btn-primary btn-sm"
                    :href="queueHref(assignedQueue.next.id)"
                    :title="assignedQueue.next.application_no"
                >
                    Next application &rarr;
                </Link>
                <button v-else class="btn-primary btn-sm" type="button" disabled>
                    Next application &rarr;
                </button>
                <span v-if="assignedQueue.total" class="self-center text-xs font-semibold uppercase tracking-wide text-gov-muted">
                    <template v-if="assignedQueue.position">{{ assignedQueue.position }} of {{ assignedQueue.total }}</template>
                    <template v-else>{{ assignedQueue.total }} remaining</template>
                    in {{ assignedQueue.step_label || 'this step' }}
                </span>
                <span v-if="application.assigned_staff" class="self-center text-xs font-semibold uppercase tracking-wide text-gov-blue">
                    Assigned staff: {{ application.assigned_staff }}
                </span>
                <StatusBadge :label="application.status_label" :tone="application.status_tone" />
                <button class="btn-ghost btn-sm sm:ml-auto" type="button" @click="showDetails = !showDetails">
                    {{ showDetails ? 'Hide progress' : 'Show progress' }}
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-4" :class="showDetails ? 'xl:grid-cols-[minmax(0,1fr)_16rem]' : ''">
            <div class="min-w-0 space-y-4">

                <!-- Initial accept / reject -->
                <div v-if="application.needs_initial_review || application.status === 'draft'" class="panel border-l-4 border-l-gov-warning">
                    <div class="panel-h">Pending applicant approval</div>
                    <div class="panel-body space-y-3 text-sm">
                        <p>
                            This application is still a draft waiting for the office to accept or reject it.
                            Accept it to let the applicant continue with the requirements
                            <template v-if="application.applicant_detail?.account_pending">
                                and to email sign-in details to
                                <strong>{{ application.applicant_detail?.email }}</strong>
                            </template>
                            <template v-else>
                                (<strong>{{ application.applicant_detail?.email }}</strong>)
                            </template>.
                        </p>
                        <div v-if="abilities.approve">
                            <label>Remarks (required when rejecting)</label>
                            <textarea v-model="initialReview.remarks" rows="2" placeholder="Optional note for acceptance, required for rejection" />
                            <p v-if="initialReview.errors.remarks" class="field-error">{{ initialReview.errors.remarks }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button class="btn-success" type="button" :disabled="initialReview.processing" @click="requestApprove">
                                    Accept application
                                </button>
                                <button class="btn-danger" type="button" :disabled="initialReview.processing" @click="requestReject">
                                    Reject application
                                </button>
                            </div>
                        </div>
                        <p v-else class="italic text-gov-muted">You do not have permission to accept or reject this application.</p>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════
                     WORKFLOW TRACKER — Shows current step + assigned staff
                     ═══════════════════════════════════════════════ -->
                <div class="panel">
                    <div class="panel-h">Workflow Progress</div>
                    <div class="panel-body flex justify-center">
                        <div class="grid grid-cols-[1fr_auto_1fr_auto_1fr] items-start gap-y-2">
                            <!-- Row 1: circles + connectors -->
                            <template v-for="(step, idx) in workflowSteps" :key="step.key">
                                <div v-if="idx > 0" class="flex items-center self-center px-2">
                                    <div class="h-1 w-16 rounded" :class="stepConnectorClass(step.state)" />
                                </div>
                                <div class="flex flex-col items-center text-center" style="min-width: 8rem;">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold" :class="stepStateClass(step.state)">
                                        <template v-if="step.state === 'done'">✓</template>
                                        <template v-else>{{ step.order }}</template>
                                    </div>
                                    <p class="mt-2 text-xs font-bold uppercase tracking-wide" :class="step.state === 'upcoming' ? 'text-gray-400' : 'text-gov-blue'">{{ step.label }}</p>
                                    <p class="mt-0.5 max-w-[9rem] text-[11px] leading-tight text-gov-muted">{{ step.description }}</p>
                                    <p v-if="step.assigned_user_name" class="mt-1 break-words rounded bg-gov-off px-2 py-0.5 text-[11px] font-semibold text-gov-blue">
                                        {{ step.assigned_user_name }}
                                    </p>
                                    <p v-else class="mt-1 text-[11px] italic text-gray-400">Unassigned</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Applicant information -->
                <div class="panel">
                    <div class="panel-h">Applicant information</div>
                    <dl class="grid grid-cols-2 gap-0 text-sm">
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Name</dt><dd>{{ application.applicant_detail?.full_name }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Beneficiary no.</dt><dd>{{ application.applicant_detail?.applicant_no }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Date of birth / sex</dt><dd>{{ application.applicant_detail?.date_of_birth }} · {{ application.applicant_detail?.sex }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Type</dt><dd>{{ application.applicant_detail?.beneficiary_label }}</dd></div>
                        <div class="col-span-2 border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Address</dt><dd>{{ application.applicant_detail?.full_address }}</dd></div>
                        <div class="px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Contact</dt><dd>{{ application.applicant_detail?.contact_number }}</dd></div>
                        <div class="px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Email</dt><dd>{{ application.applicant_detail?.email }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Mother</dt><dd>{{ application.applicant_detail?.mother_name || '—' }} · {{ application.applicant_detail?.mother_occupation || '—' }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Father</dt><dd>{{ application.applicant_detail?.father_name || '—' }} · {{ application.applicant_detail?.father_occupation || '—' }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">PWD</dt><dd>{{ application.applicant_detail?.is_pwd ? 'Yes' : 'No' }}</dd></div>
                        <div v-if="application.applicant_detail?.is_pwd" class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">PWD type</dt><dd>{{ application.applicant_detail?.pwd_type_label }}<span v-if="application.applicant_detail?.pwd_type_detail"> — {{ application.applicant_detail.pwd_type_detail }}</span></dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">School</dt><dd>{{ application.applicant_detail?.school_name || '—' }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Course / program</dt><dd>{{ application.applicant_detail?.course_or_program || '—' }}</dd></div>
                        <div class="border-b border-gov-border px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Year level</dt><dd>{{ application.applicant_detail?.year_level || '—' }}</dd></div>
                    </dl>
                </div>

                <!-- Selected program -->
                <div class="panel">
                    <div class="panel-h">Selected program</div>
                    <div class="panel-body space-y-2 text-sm">
                        <p><strong>{{ application.program?.name }}</strong> ({{ application.program?.code }}) — {{ application.program?.category_name }}</p>
                        <p>{{ application.program_detail?.description }}</p>
                        <p><strong>Eligibility:</strong> {{ application.program_detail?.eligibility }}</p>
                        <ul v-if="application.program_detail?.eligibility_rules?.length" class="list-disc space-y-1 pl-5">
                            <li v-for="rule in application.program_detail.eligibility_rules" :key="rule.id">
                                {{ rule.label }}
                                <span v-if="rule.field && rule.value" class="text-gov-muted">
                                    ({{ rule.field.replaceAll('_', ' ') }} {{ rule.operator }} {{ rule.value }})
                                </span>
                            </li>
                        </ul>
                        <p><strong>Amount:</strong> {{ application.program?.amount_display }}</p>
                    </div>
                </div>

                <!-- Submitted information -->
                <div class="panel">
                    <div class="panel-h">Submitted information</div>
                    <table class="data-table">
                        <thead><tr><th>Field</th><th>Answer</th></tr></thead>
                        <tbody>
                            <tr v-if="!application.answers?.length">
                                <td colspan="2" class="px-4 py-3 text-sm text-gov-muted">No submitted information was recorded for this application.</td>
                            </tr>
                            <tr v-for="answer in application.answers" :key="answer.field_name">
                                <td data-label="Field">{{ answer.field_label }}</td>
                                <td data-label="Answer">{{ answer.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Step 1: Requirements & Verification -->
                <div class="panel">
                    <div class="panel-h">
                        <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gov-blue text-[10px] font-bold text-white">1</span>
                        Step 1: Requirements &amp; Verification
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Upload date</th>
                                <th>Verification status</th>
                                <th>Verified by</th>
                                <th>Verification date</th>
                                <th>OCR result</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="document in application.documents" :key="document.id">
                                <td data-label="Document">
                                    {{ document.requirement_name }}<br>
                                    <button
                                        class="text-left text-gov-blue underline"
                                        type="button"
                                        @click="openViewer(document)"
                                    >{{ document.original_name }}</button>
                                    <button
                                        v-if="document.is_image"
                                        class="mt-2 block max-w-sm"
                                        type="button"
                                        @click="openViewer(document)"
                                    >
                                        <img
                                            :src="route('admin.applications.documents.preview', [application.id, document.id], false)"
                                            :alt="document.requirement_name"
                                            class="max-h-40 w-full border border-gov-border bg-gov-off object-contain"
                                        >
                                    </button>
                                </td>
                                <td data-label="Uploaded">{{ document.uploaded_at }}</td>
                                <td data-label="Status"><span class="badge" :class="`badge-${document.status_tone}`">{{ document.status_label }}</span></td>
                                <td data-label="Verifier">{{ document.verified_by || '—' }}</td>
                                <td data-label="Verified">{{ document.verified_at }}</td>
                                <td data-label="OCR">
                                    <div class="space-y-1">
                                        <span v-if="document.ocr" class="badge" :class="`badge-${document.ocr.tone}`">{{ document.ocr.overall_label }}</span>
                                        <span v-else class="text-xs text-gov-muted">Not scanned</span>
                                        <button class="block text-left text-xs text-gov-blue underline" type="button" @click="ocrDocumentId = document.id">View OCR</button>
                                    </div>
                                </td>
                                <td data-label="Remarks">{{ document.remarks || '—' }}</td>
                                <td data-label="Action">
                                    <div v-if="abilities.verify && application.is_in_verification" class="space-y-2">
                                        <textarea v-model="remarks[document.id]" rows="2" placeholder="Remarks (required to reject or request revision)" />
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                class="btn-success btn-sm"
                                                type="button"
                                                :disabled="awaitingRevision(document)"
                                                :title="awaitingRevision(document) ? 'Waiting for the applicant to replace this file' : ''"
                                                @click="verifyDocument(document.id, 'verify')"
                                            >
                                                Verify
                                            </button>
                                            <button class="btn-danger btn-sm" type="button" @click="verifyDocument(document.id, 'reject')">Reject document</button>
                                            <button
                                                class="btn-warning btn-sm"
                                                type="button"
                                                :disabled="awaitingRevision(document)"
                                                @click="verifyDocument(document.id, 'revision')"
                                            >
                                                Request revision
                                            </button>
                                        </div>
                                        <p v-if="awaitingRevision(document)" class="text-xs text-gov-warning">Waiting for a replacement upload.</p>
                                    </div>
                                    <span v-else-if="abilities.verify" class="text-xs italic text-gov-muted">Verification for this application is closed.</span>
                                    <span v-else class="text-xs italic text-gov-muted">Not assigned to this step</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="abilities.verify && application.is_in_verification" class="border-t border-gov-border px-4 py-3">
                        <p v-if="application.status === 'for_revision'" class="mb-3 text-sm text-gov-warning">
                            A document was sent back for revision. You can keep verifying the other files while the applicant uploads a replacement.
                        </p>
                        <p class="text-sm text-gov-muted">
                            <template v-if="application.can_complete_verification">All required documents are verified. Mark this step as done to send the application for evaluation.</template>
                            <template v-else>Verify every required document before you can mark this step as done.</template>
                        </p>
                        <button
                            class="btn-primary mt-3"
                            type="button"
                            :disabled="!application.can_complete_verification || completeVerification.processing"
                            @click="requestCompleteVerification"
                        >
                            Mark as done
                        </button>
                    </div>
                </div>

                <!-- Step 2: Evaluation -->
                <div class="panel">
                    <div class="panel-h">
                        <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gov-blue text-[10px] font-bold text-white">2</span>
                        Step 2: Evaluation
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Required</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!requirementRows.length">
                                <td colspan="4" class="px-4 py-3 text-sm text-gov-muted">No program requirements listed.</td>
                            </tr>
                            <tr v-for="row in requirementRows" :key="row.key">
                                <td data-label="Required">
                                    {{ row.required_name }}
                                    <span v-if="row.is_required" class="text-gov-muted"> *</span>
                                </td>
                                <td data-label="Submitted">{{ row.document?.original_name || 'Not submitted' }}</td>
                                <td data-label="Status">
                                    <span v-if="row.document" class="badge" :class="`badge-${row.document.status_tone}`">{{ row.document.status_label }}</span>
                                    <span v-else class="badge badge-danger">Missing</span>
                                </td>
                                <td data-label="">
                                    <button
                                        v-if="row.document"
                                        class="btn-ghost btn-sm"
                                        type="button"
                                        @click="openViewer(row.document)"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-if="eligibilityCheck?.rules?.length" class="data-table">
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Check</th>
                                <th>Result</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in eligibilityCheck.rules" :key="rule.id">
                                <td data-label="Rule">
                                    {{ rule.label }}
                                    <p v-if="rule.check_mode === 'ocr' && rule.evidence" class="text-xs text-gov-muted">{{ rule.evidence }}</p>
                                    <p v-if="rule.prior_records?.length" class="text-xs text-gov-muted">
                                        Prior: {{ rule.prior_records.map((record) => `${record.application_no} (${record.status_label})`).join(', ') }}
                                    </p>
                                    <p v-else-if="rule.shows_prior_records" class="text-xs text-gov-muted">No prior application found.</p>
                                </td>
                                <td data-label="Check">{{ rule.check_mode === 'manual' ? 'Manual' : 'OCR' }}</td>
                                <td data-label="Result">
                                    <span class="badge" :class="{
                                        'badge-success': ruleOutcome(rule) === 'passed',
                                        'badge-danger': ruleOutcome(rule) === 'failed',
                                        'badge-warning': ruleOutcome(rule) === 'review',
                                    }">{{ ruleOutcome(rule) === 'passed' ? 'Met' : (ruleOutcome(rule) === 'failed' ? 'Not met' : 'Pending') }}</span>
                                </td>
                                <td data-label="">
                                    <div class="flex flex-wrap gap-1">
                                        <button
                                            v-if="rule.check_mode === 'manual'"
                                            class="btn-secondary btn-sm"
                                            type="button"
                                            @click="openManualDocuments(rule)"
                                        >
                                            View documents
                                        </button>
                                        <template v-if="canDecideRules">
                                            <button
                                                class="btn-success btn-sm"
                                                type="button"
                                                :disabled="!canMarkRule(rule)"
                                                :title="canMarkRule(rule) ? '' : 'View documents first'"
                                                @click="decideRule(rule.id, 'passed')"
                                            >
                                                Met
                                            </button>
                                            <button
                                                class="btn-danger btn-sm"
                                                type="button"
                                                :disabled="!canMarkRule(rule)"
                                                :title="canMarkRule(rule) ? '' : 'View documents first'"
                                                @click="decideRule(rule.id, 'failed')"
                                            >
                                                Not met
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="combinedEligibility" class="flex flex-wrap items-center justify-between gap-2 border-t border-gov-border px-4 py-3 text-sm">
                        <span class="badge" :class="`badge-${combinedEligibility.tone}`">{{ combinedEligibility.status_label }}</span>
                        <button
                            v-if="abilities.evaluate"
                            class="btn-ghost btn-sm"
                            type="button"
                            :disabled="scanEligibility.processing"
                            @click="runEligibilityScan"
                        >
                            {{ scanEligibility.processing ? 'Scanning…' : 'Scan documents' }}
                        </button>
                    </div>

                    <dl v-if="application.latest_evaluation" class="grid gap-2 border-t border-gov-border px-4 py-3 text-sm md:grid-cols-2">
                        <div>{{ application.latest_evaluation.recommendation_label }} · {{ application.latest_evaluation.evaluator }}</div>
                        <div>{{ application.latest_evaluation.recommended_amount_formatted }} · {{ application.latest_evaluation.evaluated_at }}</div>
                    </dl>

                    <form v-if="abilities.evaluate && application.is_in_evaluation" class="grid gap-3 border-t border-gov-border p-4 md:grid-cols-2" @submit.prevent="requestSaveEvaluate">
                        <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                            <input v-model="evaluate.eligibility_passed" type="checkbox"> Eligible
                        </label>
                        <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                            <input v-model="evaluate.documents_complete" type="checkbox"> Documents complete
                        </label>
                        <div class="md:col-span-2"><label>Assessment</label><textarea v-model="evaluate.assessment" rows="2" /></div>
                        <div>
                            <label>Recommendation</label>
                            <select v-model="evaluate.recommendation" required>
                                <option value="approval">Recommend approval</option>
                                <option value="rejection">Recommend rejection</option>
                                <option value="revision">Return for revision</option>
                            </select>
                        </div>
                        <div><label>Amount</label><input v-model="evaluate.recommended_amount" type="number" step="0.01"></div>
                        <div class="md:col-span-2"><label>Remarks</label><textarea v-model="evaluate.remarks" rows="2" required /></div>
                        <div class="md:col-span-2">
                            <button class="btn-primary" type="submit" :disabled="evaluate.processing">
                                {{ evaluate.recommendation === 'revision' ? 'Save and return for revision' : 'Save and send to approval' }}
                            </button>
                        </div>
                    </form>
                    <p v-else-if="!application.latest_evaluation && !application.is_in_evaluation" class="px-4 py-3 text-sm italic text-gov-muted">Awaiting previous step.</p>
                </div>

                <!-- Step 3: Approval -->
                <div class="panel">
                    <div class="panel-h">
                        <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gov-blue text-[10px] font-bold text-white">3</span>
                        Step 3: Approval
                    </div>
                    <div class="panel-body">
                        <p v-if="application.latest_evaluation" class="mb-3 text-sm">
                            Evaluator remarks: {{ application.latest_evaluation.remarks }} · Recommended amount {{ application.latest_evaluation.recommended_amount_formatted }}
                        </p>
                        <p v-if="application.latest_approval" class="mb-3 text-sm">
                            Last decision: <strong>{{ application.latest_approval.decision_label }}</strong> by {{ application.latest_approval.officer }} on {{ application.latest_approval.decided_at }}
                        </p>
                        <div v-if="abilities.approve && application.is_in_approval" class="grid gap-3 md:grid-cols-2">
                            <div class="md:col-span-2"><label>Approved amount</label><input v-model="decide.approved_amount" type="number" step="0.01"></div>
                            <div class="md:col-span-2"><label>Remarks (required when rejecting or returning)</label><textarea v-model="decide.remarks" rows="3" /></div>
                            <div class="md:col-span-2 flex flex-wrap gap-2">
                                <button class="btn-success" type="button" @click="saveDecision('approved')">Approve</button>
                                <button class="btn-danger" type="button" @click="saveDecision('rejected')">Reject</button>
                                <button class="btn-warning" type="button" @click="saveDecision('revision')">Return for revision</button>
                            </div>
                        </div>
                        <p v-else-if="application.latest_approval" class="text-sm italic text-gov-muted">A final decision has already been recorded.</p>
                        <p v-else class="text-sm italic text-gov-muted">Not assigned to this step or awaiting previous steps.</p>
                    </div>
                </div>
            </div>

            <aside v-if="showDetails" class="min-w-0 space-y-4">
                <Timeline compact :steps="application.timeline" />

                <!-- Workflow Staff Assignments -->
                <div class="panel">
                    <div class="panel-h">Workflow Staff</div>
                    <ul class="divide-y divide-gov-border text-sm">
                        <li v-for="step in workflowSteps" :key="step.key" class="flex items-center gap-2 px-3 py-2">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white" :class="{ 'bg-green-600': step.state === 'done', 'bg-gov-blue': step.state === 'current', 'bg-gray-300': step.state === 'upcoming' }">{{ step.order }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-wide text-gov-muted">{{ step.label }}</p>
                                <p v-if="step.assigned_user_name" class="break-words font-semibold text-gov-blue">{{ step.assigned_user_name }}</p>
                                <p v-else class="italic text-gray-400">Unassigned</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Status history -->
                <div class="panel">
                    <div class="panel-h">Status history</div>
                    <ul class="divide-y divide-gov-border text-xs">
                        <li v-for="(history, index) in application.history" :key="index" class="px-3 py-2">
                            <strong>{{ history.to_status }}</strong><br>
                            {{ history.user }} · {{ history.created_at }}
                            <div v-if="history.remarks" class="text-gov-muted">{{ history.remarks }}</div>
                        </li>
                    </ul>
                </div>

                <!-- Release schedule -->
                <div v-if="application.latest_schedule" class="panel panel-body text-sm">
                    <p class="font-bold">Release schedule</p>
                    <p>{{ application.latest_schedule.release_date }} · {{ application.latest_schedule.release_location }}</p>
                    <p>{{ application.latest_schedule.method_label }}</p>
                </div>
            </aside>
        </div>

        <OcrReviewModal
            :show="Boolean(ocrDocument)"
            :application-id="application.id"
            :document="ocrDocument"
            :can-verify="abilities.verify && application.is_in_verification"
            @close="ocrDocumentId = null"
            @verify="verifyDocument(ocrDocument.id, 'verify')"
            @revision="(note) => { remarks[ocrDocument.id] = note; verifyDocument(ocrDocument.id, 'revision'); }"
            @rerun="runOcr(ocrDocument.id)"
        />

        <FileViewerModal
            :show="Boolean(viewerDocument)"
            :application-id="application.id"
            :document="viewerDocument"
            @close="closeViewer"
        />

        <ConfirmModal
            :show="initialAction === 'approve'"
            tone="success"
            title="Approve applicant"
            :message="`Approve ${application.applicant_detail?.full_name || 'this applicant'} for portal access?\n\nA temporary password will be generated and emailed to ${application.applicant_detail?.email || 'the applicant'} along with the approval confirmation.`"
            confirm-label="Approve &amp; send credentials"
            cancel-label="Cancel"
            :processing="initialReview.processing"
            @confirm="confirmInitialAction"
            @cancel="cancelInitialAction"
        />

        <ConfirmModal
            :show="initialAction === 'reject'"
            tone="danger"
            title="Reject application"
            :message="`Reject application ${application.application_no}? The applicant will be notified by email with your remarks.`"
            confirm-label="Yes, reject"
            cancel-label="Cancel"
            :processing="initialReview.processing"
            @confirm="confirmInitialAction"
            @cancel="cancelInitialAction"
        />
        <ConfirmModal
            :show="workflowAction === 'complete-verification'"
            tone="success"
            title="Mark verification as done"
            :message="`Send application ${application.application_no} to evaluation? Assigned evaluation staff will take over this record.`"
            confirm-label="Mark as done"
            cancel-label="Cancel"
            :processing="completeVerification.processing"
            @confirm="confirmWorkflowAction"
            @cancel="cancelWorkflowAction"
        />

        <ConfirmModal
            :show="workflowAction === 'submit-evaluation'"
            :tone="evaluate.recommendation === 'revision' || combinedEligibility?.eligible === false ? 'warning' : 'success'"
            :title="evaluate.recommendation === 'revision' ? 'Return for revision' : 'Save and send to approval'"
            :message="evaluationConfirmMessage"
            :confirm-label="evaluate.recommendation === 'revision' ? 'Save and return for revision' : 'Save and send to approval'"
            cancel-label="Cancel"
            :processing="evaluate.processing"
            @confirm="confirmWorkflowAction"
            @cancel="cancelWorkflowAction"
        />
    </AdminLayout>
</template>
