<script setup>
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    program: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    ocrFields: { type: Array, default: () => [] },
    eligibilityFields: { type: Array, default: () => [] },
    eligibilityOperators: { type: Array, default: () => [] },
    eligibilityCheckModes: { type: Array, default: () => [] },
});

const emptyReq = () => ({ name: '', description: '', is_required: true, ocr_fields: [] });
const emptyField = () => ({ label: '', name: '', type: 'text', options: '', help_text: '', is_required: true });
const emptyRule = () => ({ label: '', check_mode: 'ocr', field: '', operator: 'contains', value: '' });

const form = useForm({
    name: props.program?.name || '',
    code: props.program?.code || '',
    program_category_id: props.program?.program_category_id || props.categories[0]?.id || '',
    beneficiary_type: props.program?.beneficiary_type || 'both',
    description: props.program?.description || '',
    eligibility: props.program?.eligibility || '',
    amount_type: props.program?.amount_type || 'fixed',
    amount: props.program?.amount || '',
    amount_max: props.program?.amount_max || '',
    open_from: props.program?.open_from_raw || '',
    open_until: props.program?.open_until_raw || '',
    slot_limit: props.program?.slot_limit || '',
    is_open: props.program?.is_open ?? true,
    requirements: props.program?.requirements?.length
        ? props.program.requirements.map((r) => ({ name: r.name, description: r.description || '', is_required: !!r.is_required, ocr_fields: r.ocr_fields || [] }))
        : [emptyReq()],
    fields: props.program?.form_fields?.length
        ? props.program.form_fields.map((f) => ({
            label: f.label,
            name: f.name,
            type: f.type,
            options: Array.isArray(f.options) ? f.options.join(', ') : '',
            help_text: f.help_text || '',
            is_required: !!f.is_required,
        }))
        : [emptyField()],
    rules: props.program?.eligibility_rules?.length
        ? props.program.eligibility_rules.map((r) => ({
            label: r.label,
            check_mode: r.check_mode || 'ocr',
            field: r.field || '',
            operator: r.operator || 'contains',
            value: r.value || '',
        }))
        : [emptyRule()],
});

const toggleOcrField = (row, key) => {
    const current = row.ocr_fields || [];
    row.ocr_fields = current.includes(key)
        ? current.filter((item) => item !== key)
        : [...current, key];
};

const addRequirement = () => form.requirements.push(emptyReq());
const addField = () => form.fields.push(emptyField());
const addRule = () => form.rules.push(emptyRule());

const submit = () => {
    if (props.program?.id) {
        form.put(route('admin.programs.update', props.program.id));
    } else {
        form.post(route('admin.programs.store'));
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="program ? 'Edit Program' : 'Create Program'" />
        <PageHeader :title="program ? program.name : 'Create assistance program'" kicker="Program management">
            <template #actions>
                <Link class="btn-secondary btn-sm" :href="route('admin.programs.index')">Back to list</Link>
            </template>
        </PageHeader>
        <form class="space-y-4" @submit.prevent="submit">
            <div class="panel">
                <div class="panel-h">Program details</div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    <div><label>Name</label><input v-model="form.name" required></div>
                    <div><label>Code</label><input v-model="form.code" required></div>
                    <div>
                        <label>Category</label>
                        <select v-model="form.program_category_id" required>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label>Beneficiary type</label>
                        <select v-model="form.beneficiary_type">
                            <option value="student">Student</option>
                            <option value="non_student">Non-student</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="md:col-span-2"><label>Description</label><textarea v-model="form.description" rows="3" required /></div>
                    <div class="md:col-span-2"><label>Eligibility</label><textarea v-model="form.eligibility" rows="3" required /></div>
                    <div>
                        <label>Amount type</label>
                        <select v-model="form.amount_type">
                            <option value="fixed">Fixed</option>
                            <option value="up_to">Up to</option>
                            <option value="variable">Variable</option>
                        </select>
                    </div>
                    <div><label>Amount</label><input v-model="form.amount" type="number" step="0.01"></div>
                    <div><label>Maximum amount</label><input v-model="form.amount_max" type="number" step="0.01"></div>
                    <div><label>Open from</label><input v-model="form.open_from" type="date"></div>
                    <div><label>Open until</label><input v-model="form.open_until" type="date"></div>
                    <div><label>Slot limit</label><input v-model="form.slot_limit" type="number"></div>
                    <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                        <input v-model="form.is_open" type="checkbox"> Open for application
                    </label>
                </div>
            </div>
            <div class="panel">
                <div class="panel-h">Requirements</div>
                <div class="space-y-3 p-4">
                    <div v-for="(row, index) in form.requirements" :key="index" class="space-y-2 border border-gov-border p-3">
                        <div class="grid gap-2 md:grid-cols-3">
                            <input v-model="row.name" placeholder="Document name">
                            <input v-model="row.description" placeholder="Description">
                            <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                                <input v-model="row.is_required" type="checkbox"> Required
                            </label>
                        </div>
                        <div>
                            <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gov-muted">OCR should look for</p>
                            <p class="mb-2 text-[11px] font-normal normal-case tracking-normal text-gov-muted">Used to assist staff. OCR never approves or verifies a document.</p>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="field in ocrFields" :key="field.key" class="flex items-center gap-1 text-xs font-normal normal-case tracking-normal">
                                    <input
                                        type="checkbox"
                                        :checked="(row.ocr_fields || []).includes(field.key)"
                                        @change="toggleOcrField(row, field.key)"
                                    >
                                    {{ field.label }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <button class="btn-ghost btn-sm" type="button" @click="addRequirement">Add requirement</button>
                </div>
            </div>
            <div class="panel">
                <div class="panel-h">Dynamic form fields</div>
                <div class="space-y-3 p-4">
                    <p class="text-sm text-gov-muted">These questions appear on the public application form and the applicant portal. Saved answers are shown in review, staff application records, and OCR matching.</p>
                    <div v-for="(row, index) in form.fields" :key="index" class="grid gap-2 md:grid-cols-5">
                        <input v-model="row.label" placeholder="Label">
                        <select v-model="row.type">
                            <option value="text">text</option>
                            <option value="textarea">textarea</option>
                            <option value="number">number</option>
                            <option value="date">date</option>
                            <option value="select">select</option>
                        </select>
                        <input v-model="row.options" placeholder="Options (comma-separated)">
                        <input v-model="row.help_text" placeholder="Help text">
                        <label class="flex items-center gap-2 text-sm font-normal normal-case tracking-normal">
                            <input v-model="row.is_required" type="checkbox"> Required
                        </label>
                    </div>
                    <button class="btn-ghost btn-sm" type="button" @click="addField">Add field</button>
                </div>
            </div>
            <div class="panel">
                <div class="panel-h">Eligibility rules</div>
                <div class="space-y-3 p-4">
                    <p class="text-sm text-gov-muted">Choose OCR when the rule can be read from uploaded documents. Choose Manual when staff must confirm it during evaluation, such as whether the applicant already received this program.</p>
                    <div v-for="(row, index) in form.rules" :key="index" class="space-y-2 border border-gov-border p-3">
                        <input v-model="row.label" placeholder="Eligibility statement">
                        <select v-model="row.check_mode">
                            <option v-for="mode in eligibilityCheckModes" :key="mode.key" :value="mode.key">{{ mode.label }}</option>
                        </select>
                        <div v-if="row.check_mode !== 'manual'" class="grid gap-2 md:grid-cols-4">
                            <select v-model="row.field">
                                <option value="">Infer from statement</option>
                                <option v-for="field in eligibilityFields" :key="field.key" :value="field.key">{{ field.label }}</option>
                            </select>
                            <select v-model="row.operator">
                                <option v-for="operator in eligibilityOperators" :key="operator.key" :value="operator.key">{{ operator.label }}</option>
                            </select>
                            <input
                                v-if="row.operator !== 'present'"
                                v-model="row.value"
                                class="md:col-span-2"
                                placeholder="Value to find in scanned documents"
                            >
                        </div>
                        <p v-else class="text-xs text-gov-muted">Staff will mark this rule Met or Not met in Step 2. OCR will not be used.</p>
                    </div>
                    <button class="btn-ghost btn-sm" type="button" @click="addRule">Add rule</button>
                </div>
            </div>
            <button class="btn-primary" type="submit" :disabled="form.processing">Save program</button>
        </form>
    </AdminLayout>
</template>
