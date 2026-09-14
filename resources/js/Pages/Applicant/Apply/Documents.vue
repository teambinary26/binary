<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ApplySteps from '@/Components/ApplySteps.vue';
import FileViewerModal from '@/Components/FileViewerModal.vue';
import { useNotify } from '@/composables/useNotify';

const props = defineProps({
    application: { type: Object, required: true },
});

const { notify } = useNotify();
const uploadingId = ref(null);
const uploadPercent = ref(0);
const uploadLoaded = ref(0);
const uploadTotal = ref(0);
const uploadName = ref('');
const dragOverId = ref(null);
const fileErrors = ref({});
const viewerDoc = ref(null);

const formatBytes = (bytes) => {
    const size = Number(bytes) || 0;

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const requirements = computed(() => props.application.program_detail?.requirements ?? []);
const requiredList = computed(() => requirements.value.filter((item) => item.is_required));
const uploadedRequired = computed(() => requiredList.value.filter((item) => isReady(item.id)).length);
const requiredComplete = computed(() => requiredList.value.every((item) => isReady(item.id)));
const optionalList = computed(() => requirements.value.filter((item) => ! item.is_required));
const progressPercent = computed(() => {
    if (! requiredList.value.length) {
        return 100;
    }

    return Math.round((uploadedRequired.value / requiredList.value.length) * 100);
});
const missingRequired = computed(() => requiredList.value.filter((item) => ! documentFor(item.id)));

const documentFor = (requirementId) => props.application.documents.find((document) => document.requirement_id === requirementId);
const needsReplace = (document) => ['revision_requested', 'rejected'].includes(document?.status);
const isReady = (requirementId) => {
    const document = documentFor(requirementId);

    return Boolean(document) && ! needsReplace(document);
};
const revisionDocuments = computed(() => (props.application.revision_documents ?? props.application.documents.filter((document) => needsReplace(document))));
const displayedRequirements = computed(() => [...requirements.value].sort((left, right) => {
    const leftNeed = needsReplace(documentFor(left.id)) ? 0 : 1;
    const rightNeed = needsReplace(documentFor(right.id)) ? 0 : 1;

    return leftNeed - rightNeed;
}));

const downloadHref = (document) => route('applicant.applications.documents.download', [props.application.id, document.id]);

const openViewer = (document) => {
    if (document) {
        viewerDoc.value = document;
    }
};

const acceptFile = (file) => {
    const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    const nameOk = /\.(pdf|jpe?g|png)$/i.test(file.name);

    if (! allowed.includes(file.type) && ! nameOk) {
        return 'Please upload a PDF, JPG, or PNG file.';
    }

    if (file.size > 5 * 1024 * 1024) {
        return 'The file must be 5 MB or smaller.';
    }

    return null;
};

const uploadFile = (requirementId, file, input) => {
    const error = acceptFile(file);
    if (error) {
        fileErrors.value = { ...fileErrors.value, [requirementId]: error };
        notify.error(error);
        if (input) input.value = '';
        return;
    }

    fileErrors.value = { ...fileErrors.value, [requirementId]: '' };
    uploadingId.value = requirementId;
    uploadPercent.value = 0;
    uploadLoaded.value = 0;
    uploadTotal.value = file.size;
    uploadName.value = file.name;

    const form = useForm({
        requirement_id: requirementId,
        file,
    });

    form.post(route('applicant.apply.documents.store', props.application.id), {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (event) => {
            const total = event.total || file.size || 0;
            const loaded = event.loaded || 0;
            uploadLoaded.value = loaded;
            uploadTotal.value = total;

            if (typeof event.percentage === 'number') {
                uploadPercent.value = Math.min(100, Math.round(event.percentage));
            } else if (total > 0) {
                uploadPercent.value = Math.min(100, Math.round((loaded / total) * 100));
            } else if (typeof event.progress === 'number') {
                uploadPercent.value = Math.min(100, Math.round(event.progress <= 1 ? event.progress * 100 : event.progress));
            }
        },
        onError: (errors) => {
            const message = errors.file || errors.requirement_id || 'Could not upload that file.';
            fileErrors.value = { ...fileErrors.value, [requirementId]: message };
            notify.error(message);
        },
        onFinish: () => {
            uploadPercent.value = 100;
            uploadLoaded.value = uploadTotal.value || file.size;
            uploadingId.value = null;
            uploadName.value = '';
            if (input) input.value = '';
        },
    });
};

const onFileChange = (requirementId, event) => {
    const file = event.target.files?.[0];
    if (file) {
        uploadFile(requirementId, file, event.target);
    }
};

const onDrop = (requirementId, event) => {
    event.preventDefault();
    dragOverId.value = null;
    if (uploadingId.value) return;
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        uploadFile(requirementId, file);
    }
};

const onDragLeave = (requirementId, event) => {
    if (! event.currentTarget.contains(event.relatedTarget)) {
        if (dragOverId.value === requirementId) {
            dragOverId.value = null;
        }
    }
};

const pickFile = (requirementId) => {
    if (uploadingId.value) return;
    document.getElementById(`file_${requirementId}`)?.click();
};

const jumpTo = (requirementId) => {
    document.getElementById(`requirement-${requirementId}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <ApplicantLayout>
        <Head title="Upload Requirements" />
        <PageHeader :title="application.program?.name" kicker="Step 3 — Requirements" :document-no="application.application_no" />
        <ApplySteps :application-id="application.id" />

        <div class="panel mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3 bg-gov-blue px-3 py-3 text-white sm:px-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/75">Required documents</p>
                    <p class="mt-0.5 text-xl font-bold leading-tight">{{ uploadedRequired }} of {{ requiredList.length }} uploaded</p>
                </div>
                <span
                    class="badge"
                    :class="requiredComplete ? 'border-white bg-white text-gov-success' : 'border-white/40 bg-white/10 text-white'"
                >
                    {{ requiredComplete ? 'Ready for review' : `${progressPercent}% complete` }}
                </span>
            </div>
            <div class="h-2 bg-gov-light">
                <div class="h-2 bg-gov-gold transition-all" :style="{ width: `${progressPercent}%` }" />
            </div>
            <div class="space-y-3 px-3 py-3 sm:px-4">
                <p class="text-sm text-gov-muted">
                    Upload a clear PDF, JPG, or PNG (maximum 5 MB) for each requirement. Click a card to browse, or drag a file onto it. You can replace a file at any time.
                </p>
                <div v-if="requirements.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="requirement in requirements"
                        :key="`chip-${requirement.id}`"
                        class="inline-flex items-center gap-1.5 border px-2 py-1 text-[11px] font-bold uppercase tracking-wide"
                        :class="needsReplace(documentFor(requirement.id))
                            ? 'border-gov-warning bg-[#fff6e0] text-[#8a6400]'
                            : (documentFor(requirement.id)
                                ? 'border-gov-success bg-[#e8f6ee] text-gov-success'
                                : (requirement.is_required ? 'border-gov-warning bg-[#fff6e0] text-[#8a6400]' : 'border-gov-border bg-gov-off text-gov-muted'))"
                        type="button"
                        @click="jumpTo(requirement.id)"
                    >
                        <span aria-hidden="true">{{ needsReplace(documentFor(requirement.id)) ? '!' : (documentFor(requirement.id) ? '✓' : '○') }}</span>
                        {{ requirement.name }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="revisionDocuments.length" class="panel mb-4 border-l-4 border-l-gov-warning">
            <div class="panel-h">Revision requested</div>
            <div class="panel-body space-y-2 text-sm">
                <p>The office asked you to replace the following document{{ revisionDocuments.length === 1 ? '' : 's' }}. Upload a new file. When every replacement is in, the application returns to verification.</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li v-for="document in revisionDocuments" :key="document.id">
                        <button class="font-semibold text-gov-blue underline" type="button" @click="jumpTo(document.requirement_id)">{{ document.requirement_name }}</button>
                        <span v-if="document.remarks" class="text-gov-muted"> — {{ document.remarks }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="space-y-3">
            <article
                v-for="(requirement, index) in displayedRequirements"
                :id="`requirement-${requirement.id}`"
                :key="requirement.id"
                class="panel border-l-4"
                :class="needsReplace(documentFor(requirement.id))
                    ? 'border-l-gov-warning'
                    : (documentFor(requirement.id)
                        ? 'border-l-gov-success'
                        : (requirement.is_required ? 'border-l-gov-warning' : 'border-l-gov-border'))"
            >
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gov-border px-3 py-3 sm:px-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center text-xs font-bold"
                            :class="documentFor(requirement.id) ? 'bg-gov-success text-white' : 'bg-gov-blue text-white'"
                        >
                            {{ String(index + 1).padStart(2, '0') }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="break-words text-sm font-bold uppercase tracking-wide text-gov-dark">{{ requirement.name }}</h2>
                            <p v-if="requirement.description" class="mt-1 break-words text-sm text-gov-muted">{{ requirement.description }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <span v-if="requirement.is_required" class="badge badge-danger">Required</span>
                        <span v-else class="badge badge-neutral">Optional</span>
                        <span v-if="needsReplace(documentFor(requirement.id))" class="badge badge-warning">{{ documentFor(requirement.id).status_label }}</span>
                        <span v-else-if="documentFor(requirement.id)" class="badge badge-success">Uploaded</span>
                        <span v-else class="badge badge-warning">Not uploaded</span>
                    </div>
                </div>

                <div class="grid md:grid-cols-2">
                    <div
                        class="relative flex min-h-[9.5rem] cursor-pointer flex-col items-center justify-center border-b border-gov-border px-3 py-6 text-center transition-colors sm:min-h-[12rem] sm:px-4 md:min-h-[13rem] md:border-b-0 md:border-r"
                        :class="[
                            uploadingId === requirement.id ? 'pointer-events-none bg-gov-light' : '',
                            dragOverId === requirement.id ? 'bg-gov-light' : 'bg-gov-off',
                            fileErrors[requirement.id] ? 'ring-2 ring-inset ring-gov-danger' : '',
                        ]"
                        role="button"
                        tabindex="0"
                        :aria-label="documentFor(requirement.id) ? `Replace ${requirement.name}` : `Upload ${requirement.name}`"
                        @click="pickFile(requirement.id)"
                        @keydown.enter.prevent="pickFile(requirement.id)"
                        @keydown.space.prevent="pickFile(requirement.id)"
                        @dragover.prevent="dragOverId = requirement.id"
                        @dragleave="onDragLeave(requirement.id, $event)"
                        @drop="onDrop(requirement.id, $event)"
                    >
                        <input
                            :id="`file_${requirement.id}`"
                            class="sr-only"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            :disabled="uploadingId === requirement.id"
                            @click.stop
                            @change="onFileChange(requirement.id, $event)"
                        >

                        <div
                            v-if="dragOverId === requirement.id"
                            class="pointer-events-none absolute inset-1 flex items-center justify-center border-2 border-dashed border-gov-blue bg-white/90"
                        >
                            <p class="text-sm font-bold uppercase tracking-wide text-gov-blue">Drop file to upload</p>
                        </div>

                        <template v-else-if="uploadingId === requirement.id">
                            <p class="text-2xl font-bold tabular-nums text-gov-blue">{{ uploadPercent }}%</p>
                            <p class="mt-1 text-sm font-semibold text-gov-dark">Uploading…</p>
                            <p class="mt-1 max-w-full truncate px-2 text-xs text-gov-muted" :title="uploadName">{{ uploadName }}</p>
                            <div class="mt-3 h-2 w-full max-w-xs bg-gov-border" role="progressbar" :aria-valuenow="uploadPercent" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-2 bg-gov-blue transition-[width] duration-150" :style="{ width: `${uploadPercent}%` }" />
                            </div>
                            <p class="mt-2 text-xs tabular-nums text-gov-muted">
                                {{ formatBytes(uploadLoaded) }} of {{ formatBytes(uploadTotal) }}
                            </p>
                        </template>

                        <template v-else>
                            <svg class="mb-3 h-9 w-9 text-gov-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V7m0 0 3.5 3.5M12 7 8.5 10.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 16.5V18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-1.5" />
                            </svg>
                            <p class="text-sm font-semibold text-gov-dark">
                                <template v-if="needsReplace(documentFor(requirement.id))">Upload a replacement</template>
                                <template v-else-if="documentFor(requirement.id)">Replace this file</template>
                                <template v-else>
                                    <span class="sm:hidden">Tap to choose a file</span>
                                    <span class="hidden sm:inline">Drop a file here or click to browse</span>
                                </template>
                            </p>
                            <p class="mt-1 text-xs text-gov-muted">PDF, JPG, or PNG · maximum 5 MB</p>
                            <span class="btn-secondary btn-sm mt-3 pointer-events-none">
                                {{ needsReplace(documentFor(requirement.id)) || documentFor(requirement.id) ? 'Choose replacement' : 'Choose file' }}
                            </span>
                            <p v-if="fileErrors[requirement.id]" class="field-error mt-2">{{ fileErrors[requirement.id] }}</p>
                        </template>
                    </div>

                    <div
                        v-if="documentFor(requirement.id)"
                        class="flex flex-col bg-white md:min-h-[13rem]"
                    >
                        <button
                            class="flex items-center justify-center bg-gov-off px-3 py-3"
                            type="button"
                            :aria-label="`View ${requirement.name}`"
                            @click="openViewer(documentFor(requirement.id))"
                        >
                            <img
                                v-if="documentFor(requirement.id).is_image && documentFor(requirement.id).url"
                                :src="documentFor(requirement.id).url"
                                :alt="documentFor(requirement.id).original_name"
                                class="max-h-36 max-w-full object-contain sm:max-h-40"
                            >
                            <div v-else class="flex flex-col items-center gap-2 px-4 py-4 text-center">
                                <svg class="h-10 w-10 text-gov-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                                </svg>
                                <p class="text-sm font-semibold text-gov-dark">{{ documentFor(requirement.id).is_pdf ? 'PDF uploaded' : 'File uploaded' }}</p>
                                <p class="text-xs text-gov-muted">Tap to view</p>
                            </div>
                        </button>
                        <div class="flex flex-col gap-2 border-t border-gov-border px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="break-all text-xs font-semibold text-gov-dark" :title="documentFor(requirement.id).original_name">
                                    {{ documentFor(requirement.id).original_name }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-gov-muted">Uploaded {{ documentFor(requirement.id).uploaded_at }}</p>
                                <p v-if="needsReplace(documentFor(requirement.id)) && documentFor(requirement.id).remarks" class="mt-1 text-xs text-gov-warning">
                                    Staff remarks: {{ documentFor(requirement.id).remarks }}
                                </p>
                                <p v-if="documentFor(requirement.id)?.ocr?.type_matches === false" class="mt-1 text-xs text-gov-warning">
                                    This file may not be a {{ requirement.name }}. Please upload the correct document.
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    class="btn-secondary btn-sm flex-1 sm:flex-none"
                                    type="button"
                                    @click="openViewer(documentFor(requirement.id))"
                                >
                                    View
                                </button>
                                <a
                                    class="btn-ghost btn-sm flex-1 text-center sm:flex-none"
                                    :href="downloadHref(documentFor(requirement.id))"
                                    @click.stop
                                >
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex min-h-[8rem] flex-col items-center justify-center bg-white px-4 py-6 text-center md:min-h-[13rem]"
                    >
                        <svg class="mb-2 h-9 w-9 text-gov-border" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                        </svg>
                        <p class="text-sm font-semibold text-gov-dark">No preview yet</p>
                        <p class="mt-1 max-w-[16rem] text-xs text-gov-muted">
                            {{ requirement.is_required ? 'This document is required before you can continue.' : 'Optional. You can skip this if you do not have it.' }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <div class="mt-4 mb-2 border border-gov-border bg-white px-3 py-3 sm:px-4 md:sticky md:bottom-0 md:z-10 md:shadow-[0_-8px_16px_rgba(0,31,69,0.06)]">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p class="text-sm text-gov-muted">
                    <template v-if="revisionDocuments.length">Replace the highlighted document{{ revisionDocuments.length === 1 ? '' : 's' }}. The application returns to verification after the last replacement.</template>
                    <template v-else-if="requiredComplete">All required documents are in. Continue to review your application.</template>
                    <template v-else>
                        {{ missingRequired.length }} required {{ missingRequired.length === 1 ? 'document is' : 'documents are' }} still missing
                        <span v-if="optionalList.length" class="text-gov-muted"> · {{ optionalList.length }} optional</span>
                    </template>
                </p>
                <button v-if="!requiredComplete" class="btn-primary w-full sm:w-auto" type="button" disabled>Continue to review</button>
                <Link v-else class="btn-primary w-full text-center sm:w-auto" :href="route('applicant.apply.review', application.id)">Continue to review</Link>
            </div>
        </div>

        <FileViewerModal
            :show="Boolean(viewerDoc)"
            :application-id="application.id"
            :document="viewerDoc"
            :preview-href="viewerDoc ? route('applicant.applications.documents.preview', [application.id, viewerDoc.id], false) : null"
            :download-href="viewerDoc ? downloadHref(viewerDoc) : null"
            @close="viewerDoc = null"
        />
    </ApplicantLayout>
</template>
