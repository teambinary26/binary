<script setup>
import { computed, onUnmounted, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    show: { type: Boolean, default: false },
    applicationId: { type: [Number, String], default: null },
    document: { type: Object, default: null },
    previewHref: { type: String, default: null },
    downloadHref: { type: String, default: null },
});

const emit = defineEmits(['close']);

const previewUrl = computed(() => {
    if (props.previewHref) {
        return props.previewHref;
    }

    if (! props.document || props.applicationId == null) {
        return null;
    }

    return route('admin.applications.documents.preview', [props.applicationId, props.document.id], false);
});

const downloadUrl = computed(() => {
    if (props.downloadHref) {
        return props.downloadHref;
    }

    if (! props.document || props.applicationId == null) {
        return null;
    }

    return route('admin.applications.documents.download', [props.applicationId, props.document.id]);
});

const canPreview = computed(() => Boolean(
    props.document?.is_image || props.document?.is_pdf || props.document?.is_text,
));

const close = () => emit('close');

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

watch(() => props.show, (open) => {
    if (open) {
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeydown);
    } else {
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && document"
            class="fixed inset-0 z-[80] flex items-end justify-center p-0 sm:items-center sm:p-4"
            role="presentation"
        >
            <div class="absolute inset-0 bg-gov-navy/60" @click="close" />
            <div
                class="modal relative z-10 flex max-h-[100dvh] w-full max-w-5xl flex-col border border-gov-border bg-white shadow-xl sm:max-h-[92vh]"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="'file-viewer-title'"
            >
                <div class="flex items-start justify-between gap-3 bg-gov-blue px-3 py-3 sm:items-center sm:px-4">
                    <div class="min-w-0">
                        <h2 id="file-viewer-title" class="truncate text-sm font-bold uppercase tracking-wide text-white">{{ document.requirement_name }}</h2>
                        <p class="truncate text-xs text-white/80">{{ document.original_name }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a :href="downloadUrl" class="btn-secondary btn-sm bg-white">Download</a>
                        <button class="text-2xl leading-none text-white/80 hover:text-white" type="button" aria-label="Close" @click="close">&times;</button>
                    </div>
                </div>
                <div class="min-h-[16rem] flex-1 overflow-auto bg-gov-off p-3 sm:min-h-[24rem] sm:p-4">
                    <img
                        v-if="document.is_image"
                        :src="previewUrl"
                        :alt="document.requirement_name"
                        class="mx-auto max-h-[55vh] w-auto max-w-full border border-gov-border bg-white object-contain sm:max-h-[70vh]"
                    >
                    <iframe
                        v-else-if="canPreview"
                        :src="previewUrl"
                        class="h-[55vh] w-full border border-gov-border bg-white sm:h-[70vh]"
                        :title="document.original_name"
                    />
                    <div v-else class="flex h-[16rem] flex-col items-center justify-center gap-3 text-center text-sm text-gov-muted sm:h-[24rem]">
                        <p>This file type cannot be previewed in the browser.</p>
                        <a :href="downloadUrl" class="btn-primary btn-sm">Download file</a>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
