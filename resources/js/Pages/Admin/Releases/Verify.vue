<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    result: { type: Object, default: null },
    filters: { type: Object, default: () => ({ method: 'application_no', lookup: '' }) },
});

const form = useForm({
    method: props.filters.method || 'application_no',
    lookup: props.filters.lookup || '',
});

const placeholders = {
    application_no: 'CAMS-2026-000001',
    reference_no: 'REL-2026-000001',
    qr: 'Enter the verification code',
};

const hints = {
    application_no: 'Use the CAMS application number on the release slip or disbursement register.',
    reference_no: 'Use the REL reference number assigned when the assistance was released.',
    qr: 'Use the verification code printed on the release record or QR slip.',
};

const placeholder = computed(() => placeholders[form.method] || placeholders.application_no);
const hint = computed(() => hints[form.method] || hints.application_no);

const resultTone = computed(() => {
    if (! props.result) {
        return 'neutral';
    }

    if (props.result.result === 'already_claimed') {
        return 'warning';
    }

    return props.result.valid ? 'success' : 'danger';
});

const resultLabel = computed(() => String(props.result?.result || '').replaceAll('_', ' ').toUpperCase());

const flashClass = computed(() => ({
    success: 'flash-success',
    warning: 'flash-warning',
    danger: 'flash-danger',
}[resultTone.value] || 'flash-info'));

const applicationId = computed(() => props.result?.release?.application?.id || null);

const submit = () => form.post(route('admin.releases.verify.store'), { preserveScroll: true });
</script>

<template>
    <AdminLayout>
        <Head title="Claim Verification" />
        <PageHeader title="Claim Verification" kicker="Release management" />

        <form class="panel mb-4" @submit.prevent="submit">
            <div class="panel-h">Look up a released claim</div>
            <div class="grid gap-3 p-4 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label for="verify-method">Lookup method</label>
                    <select id="verify-method" v-model="form.method">
                        <option value="application_no">Application number</option>
                        <option value="reference_no">Reference number</option>
                        <option value="qr">QR / verification code</option>
                    </select>
                    <p v-if="form.errors.method" class="field-error">{{ form.errors.method }}</p>
                </div>
                <div class="md:col-span-6">
                    <label for="verify-lookup">Value</label>
                    <input
                        id="verify-lookup"
                        v-model="form.lookup"
                        required
                        autocomplete="off"
                        :placeholder="placeholder"
                    >
                    <p v-if="form.errors.lookup" class="field-error">{{ form.errors.lookup }}</p>
                </div>
                <div class="flex items-end md:col-span-3">
                    <button class="btn-primary w-full" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Verifying…' : 'Verify' }}
                    </button>
                </div>
            </div>
            <p class="border-t border-gov-border px-4 py-3 text-xs text-gov-muted">{{ hint }}</p>
        </form>

        <div v-if="result" class="space-y-4">
            <div class="flash mb-0" :class="flashClass">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <StatusBadge :label="resultLabel" :tone="resultTone" />
                    <p>{{ result.message }}</p>
                </div>
            </div>

            <div v-if="result.release" class="panel">
                <div class="panel-h flex flex-wrap items-center justify-between gap-2">
                    <span>Release record</span>
                    <StatusBadge
                        :label="result.release.status_label"
                        :tone="result.release.status_tone"
                    />
                </div>
                <dl class="grid gap-px bg-gov-border text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Applicant</dt>
                        <dd class="mt-1 break-words font-semibold">{{ result.release.applicant_name }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Application no.</dt>
                        <dd class="mt-1 break-all">{{ result.release.application?.application_no || '—' }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Program</dt>
                        <dd class="mt-1 break-words">{{ result.release.program_name }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Approved amount</dt>
                        <dd class="mt-1">{{ result.release.amount_formatted }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Reference no.</dt>
                        <dd class="mt-1 break-all">{{ result.release.reference_no }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Verification code</dt>
                        <dd class="mt-1 break-all">{{ result.release.verification_code }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Released</dt>
                        <dd class="mt-1">{{ result.release.released_at }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Released by</dt>
                        <dd class="mt-1">{{ result.release.officer || '—' }}</dd>
                    </div>
                    <div class="bg-white px-4 py-3">
                        <dt class="text-xs font-bold uppercase tracking-wide text-gov-muted">Application status</dt>
                        <dd class="mt-1">
                            <StatusBadge
                                :label="result.release.status_label"
                                :tone="result.release.status_tone"
                            />
                        </dd>
                    </div>
                </dl>
                <div v-if="applicationId" class="border-t border-gov-border px-4 py-3">
                    <Link class="btn-secondary btn-sm" :href="route('admin.applications.show', applicationId)">
                        Open application
                    </Link>
                </div>
            </div>
        </div>

        <div v-else class="panel">
            <div class="panel-h">How to verify a claim</div>
            <div class="panel-body space-y-3">
                <p class="text-sm text-gov-muted">
                    Confirm a released assistance record before turning over cash or goods. Choose a lookup method, enter the matching number or code, then verify.
                </p>
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="border border-gov-border p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-gov-muted">Application number</p>
                        <p class="mt-1 text-sm font-semibold">CAMS-YYYY-######</p>
                        <p class="mt-1 text-xs text-gov-muted">From the application or release slip.</p>
                    </div>
                    <div class="border border-gov-border p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-gov-muted">Reference number</p>
                        <p class="mt-1 text-sm font-semibold">REL-YYYY-######</p>
                        <p class="mt-1 text-xs text-gov-muted">From Released Assistance / the disbursement register.</p>
                    </div>
                    <div class="border border-gov-border p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-gov-muted">QR / verification code</p>
                        <p class="mt-1 text-sm font-semibold">Printed code</p>
                        <p class="mt-1 text-xs text-gov-muted">From the release record verification column.</p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
