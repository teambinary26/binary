<script setup>
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Timeline from '@/Components/Timeline.vue';

const props = defineProps({
    application: { type: Object, required: true },
});

const qrCanvas = ref(null);

onMounted(async () => {
    const code = props.application.latest_release?.verification_code;
    if (qrCanvas.value && code) {
        await QRCode.toCanvas(qrCanvas.value, code, { width: 128, margin: 1 });
    }
});
</script>

<template>
    <ApplicantLayout>
        <Head :title="application.application_no" />
        <PageHeader :title="application.application_no" kicker="Application tracking" :document-no="application.program?.name">
            <template #actions>
                <Link v-if="application.can_edit" class="btn-primary btn-sm w-full text-center sm:w-auto" :href="application.continue_path || route('applicant.apply.documents', application.id)">
                    {{ application.needs_document_action ? 'Replace documents' : 'Continue / upload requirements' }}
                </Link>
            </template>
        </PageHeader>
        <div class="mb-4">
            <StatusBadge :label="application.status_label" :tone="application.status_tone" :description="application.status_description" />
        </div>
        <p class="mb-4 text-sm break-words text-gov-muted">{{ application.status_description }}</p>
        <div v-if="application.revision_documents?.length" class="panel mb-4 border-l-4 border-l-gov-warning">
            <div class="panel-h">Documents to replace</div>
            <div class="panel-body space-y-2 text-sm">
                <p>The office asked you to replace these files. After you upload them, the application returns to verification.</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li v-for="document in application.revision_documents" :key="document.id">
                        <strong>{{ document.requirement_name }}</strong>
                        <span v-if="document.remarks" class="text-gov-muted"> — {{ document.remarks }}</span>
                    </li>
                </ul>
                <Link class="btn-primary btn-sm mt-2 inline-flex" :href="route('applicant.apply.documents', application.id)">Replace documents</Link>
            </div>
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <Timeline :steps="application.timeline" />
                <div v-if="application.latest_release" class="panel mt-4">
                    <div class="panel-h">Release record</div>
                    <div class="panel-body text-sm">
                        <p>Reference: <strong>{{ application.latest_release.reference_no }}</strong></p>
                        <p>Amount: {{ application.latest_release.amount_formatted }}</p>
                        <p>Released: {{ application.latest_release.released_at }}</p>
                        <p>Verification code: <strong>{{ application.latest_release.verification_code }}</strong></p>
                        <canvas ref="qrCanvas" class="mt-3 inline-block max-w-full border border-gov-border bg-white p-2" />
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="panel">
                    <div class="panel-h">Summary</div>
                    <dl class="divide-y divide-gov-border text-sm">
                        <div class="px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Program</dt><dd class="break-words">{{ application.program?.name }}</dd></div>
                        <div class="px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Submitted</dt><dd class="break-words">{{ application.submitted_at_full }}</dd></div>
                        <div class="px-4 py-2"><dt class="text-xs font-bold uppercase text-gov-muted">Approved amount</dt><dd class="break-words">{{ application.approved_amount_formatted }}</dd></div>
                    </dl>
                </div>
                <div v-if="application.history?.length" class="panel">
                    <div class="panel-h">Status history</div>
                    <ul class="divide-y divide-gov-border text-xs">
                        <li v-for="(history, index) in application.history" :key="index" class="px-4 py-2">
                            <strong>{{ history.to_status }}</strong><br>
                            {{ history.created_at }}
                            <div v-if="history.remarks" class="text-gov-muted">{{ history.remarks }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </ApplicantLayout>
</template>
