<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    show: { type: Boolean, default: false },
    applicationId: { type: [Number, String], default: null },
    document: { type: Object, default: null },
    canVerify: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'verify', 'revision', 'rerun']);

const fields = ref([]);
const remarks = ref('');

watch(
    () => props.document,
    (document) => {
        fields.value = (document?.ocr?.fields ?? []).map((field) => ({
            id: field.id,
            key: field.key,
            label: field.label,
            expected_value: field.expected_value,
            extracted_value: field.extracted_value,
            corrected_value: field.display_value || '',
            match_status: field.match_status,
            match_score: field.match_score,
        }));
        remarks.value = '';
    },
    { immediate: true },
);

const saveForm = useForm({ fields: [] });

const saveFields = () => {
    saveForm.fields = fields.value.map((field) => ({
        id: field.id,
        corrected_value: field.corrected_value,
    }));
    saveForm.put(route('admin.applications.documents.ocr.update', [props.applicationId, props.document.id]), {
        preserveScroll: true,
    });
};

const previewUrl = () => {
    if (! props.document || props.applicationId == null) {
        return null;
    }

    return route('admin.applications.documents.preview', [props.applicationId, props.document.id], false);
};

const requestRevision = () => emit('revision', remarks.value.trim());

const close = () => emit('close');
</script>

<template>
    <div v-if="show && document" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="close">
        <div class="w-full max-w-4xl border border-gov-border bg-white">
            <div class="flex items-start justify-between border-b border-gov-border px-4 py-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gov-muted">Intelligent document verification</p>
                    <h2 class="text-lg font-bold text-gov-dark">{{ document.requirement_name }}</h2>
                    <p class="text-xs text-gov-muted">OCR assists staff. It does not approve or verify the document.</p>
                </div>
                <button class="btn-ghost btn-sm" type="button" @click="close">Close</button>
            </div>

            <div class="grid gap-0 sm:grid-cols-2">
                <div class="border-b border-gov-border bg-gov-off p-4 sm:border-b-0 sm:border-r">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gov-muted">Document image</p>
                    <img
                        v-if="document.is_image"
                        :src="previewUrl()"
                        :alt="document.requirement_name"
                        class="max-h-[22rem] w-full border border-gov-border bg-white object-contain"
                    >
                    <iframe
                        v-else-if="document.is_pdf"
                        :src="previewUrl()"
                        class="h-[22rem] w-full border border-gov-border bg-white"
                        title="Document preview"
                    />
                    <p v-else class="border border-gov-border bg-white p-4 text-sm">{{ document.original_name }}</p>
                </div>

                <div class="space-y-4 p-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-gov-muted">OCR result</p>
                        <p v-if="document.ocr" class="mt-1">
                            <span class="badge" :class="`badge-${document.ocr.tone}`">{{ document.ocr.overall_label }}</span>
                        </p>
                        <p class="mt-2 text-sm">{{ document.ocr?.summary || 'OCR has not been run for this file.' }}</p>
                        <p v-if="document.ocr && document.ocr.type_matches === false" class="mt-2 text-sm text-gov-warning">
                            Required: {{ document.ocr.expected_type?.replaceAll('_', ' ') }} · Detected: {{ document.ocr.detected_type?.replaceAll('_', ' ') }}
                        </p>
                        <p v-if="document.ocr?.error_message" class="mt-2 text-sm text-gov-danger">{{ document.ocr.error_message }}</p>
                        <details v-if="document.ocr?.raw_text" class="mt-3 border border-gov-border p-2">
                            <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-gov-muted">Text read from document</summary>
                            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs text-gov-dark">{{ document.ocr.raw_text }}</pre>
                        </details>
                    </div>

                    <div v-if="fields.length">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gov-muted">Match results</p>
                        <div class="space-y-3">
                            <div v-for="field in fields" :key="field.id" class="border border-gov-border p-3">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <strong class="text-sm">{{ field.label }}</strong>
                                    <span class="badge" :class="{
                                        'badge-success': field.match_status === 'matched',
                                        'badge-warning': field.match_status === 'mismatch' || field.match_status === 'missing',
                                        'badge-info': field.match_status === 'extracted' || field.match_status === 'manual',
                                    }">
                                        {{ field.match_status === 'matched' ? '✓ Matched' : field.match_status.replaceAll('_', ' ') }}
                                    </span>
                                </div>
                                <p class="text-xs text-gov-muted">Application: {{ field.expected_value || '—' }}</p>
                                <p class="text-xs text-gov-muted">OCR extracted: {{ field.extracted_value || '—' }}</p>
                                <p v-if="field.match_status === 'matched' && field.key === 'date_of_birth'" class="text-xs text-gov-success">
                                    Same day, month, and year — format difference is ignored.
                                </p>
                                <label class="mt-2 block text-xs">Staff correction</label>
                                <input v-model="field.corrected_value" class="mt-1 w-full">
                            </div>
                        </div>
                    </div>

                    <div v-if="canVerify" class="flex flex-wrap gap-2">
                        <button class="btn-secondary btn-sm" type="button" :disabled="saveForm.processing" @click="saveFields">Save OCR corrections</button>
                        <button class="btn-ghost btn-sm" type="button" @click="emit('rerun')">Run OCR again</button>
                    </div>

                    <div v-if="canVerify" class="border-t border-gov-border pt-3">
                        <label>Remarks (required to reject or request revision)</label>
                        <textarea v-model="remarks" rows="2" class="mt-1 w-full" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                class="btn-success btn-sm"
                                type="button"
                                :disabled="document.status === 'revision_requested'"
                                @click="emit('verify')"
                            >
                                Verify document
                            </button>
                            <button class="btn-warning btn-sm" type="button" @click="requestRevision">Request revision</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
