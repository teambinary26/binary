<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    result: { type: Object, default: null },
});

const form = useForm({
    method: 'application_no',
    lookup: '',
});

const submit = () => form.post(route('admin.releases.verify.store'));
</script>

<template>
    <AdminLayout>
        <Head title="Claim Verification" />
        <PageHeader title="Release / Claim Verification" kicker="Application number, QR code, or reference number" />
        <form class="panel mb-4" @submit.prevent="submit">
            <div class="grid gap-3 p-4 md:grid-cols-3">
                <div>
                    <label>Lookup method</label>
                    <select v-model="form.method">
                        <option value="application_no">Application number</option>
                        <option value="reference_no">Reference number</option>
                        <option value="qr">QR / verification code</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label>Value</label>
                    <input v-model="form.lookup" required placeholder="CAMS-2026-000001 / REL-2026-000001 / code">
                </div>
            </div>
            <div class="px-4 pb-4"><button class="btn-primary" type="submit">Verify</button></div>
        </form>
        <div v-if="result" class="panel">
            <div class="panel-h">Verification result</div>
            <div class="panel-body">
                <p class="badge" :class="result.valid ? 'badge-success' : 'badge-danger'">{{ String(result.result || '').replace(/_/g, ' ').toUpperCase() }}</p>
                <p class="mt-3 text-sm">{{ result.message }}</p>
                <dl v-if="result.release" class="mt-4 grid gap-2 text-sm md:grid-cols-2">
                    <div><strong>Applicant:</strong> {{ result.release.applicant_name }}</div>
                    <div><strong>Program:</strong> {{ result.release.program_name }}</div>
                    <div><strong>Approved amount:</strong> {{ result.release.amount_formatted }}</div>
                    <div>
                        <strong>Release status:</strong>
                        <StatusBadge :label="result.release.status_label" :tone="result.release.status_tone" />
                    </div>
                    <div><strong>Release date:</strong> {{ result.release.released_at }}</div>
                    <div><strong>Reference:</strong> {{ result.release.reference_no }}</div>
                </dl>
            </div>
        </div>
    </AdminLayout>
</template>
