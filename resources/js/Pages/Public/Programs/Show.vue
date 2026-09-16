<script setup>
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    program: { type: Object, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head :title="program.name" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">{{ program.category?.group_label }} · {{ program.code }}</p>
                <h1 class="mt-1">{{ program.name }}</h1>
            </div>
        </div>
        <div class="mx-auto grid max-w-7xl gap-4 px-4 py-6 md:py-8 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="panel">
                    <div class="panel-h">Program information</div>
                    <div class="panel-body space-y-3 text-sm">
                        <p>{{ program.description }}</p>
                        <p><strong>Eligibility:</strong> {{ program.eligibility }}</p>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-h">Documentary requirements</div>
                    <ul class="divide-y divide-gov-border">
                        <li v-for="requirement in program.requirements" :key="requirement.id" class="px-4 py-3 text-sm">
                            <p class="font-bold">{{ requirement.name }} <span v-if="requirement.is_required" class="badge badge-danger">Required</span></p>
                            <p v-if="requirement.description" class="text-gov-muted">{{ requirement.description }}</p>
                        </li>
                    </ul>
                </div>
                <div v-if="program.form_fields?.length" class="panel">
                    <div class="panel-h">Application questions</div>
                    <ul class="divide-y divide-gov-border">
                        <li v-for="field in program.form_fields" :key="field.name" class="px-4 py-3 text-sm">
                            <p class="font-bold">{{ field.label }} <span v-if="field.is_required" class="badge badge-danger">Required</span></p>
                            <p v-if="field.help_text" class="text-gov-muted">{{ field.help_text }}</p>
                        </li>
                    </ul>
                </div>
                <div class="panel">
                    <div class="panel-h">Eligibility checklist</div>
                    <ul class="list-disc px-8 py-4 text-sm">
                        <li v-for="rule in program.eligibility_rules" :key="rule.id">{{ rule.label }}</li>
                    </ul>
                </div>
            </div>
            <aside class="space-y-4">
                <div class="panel">
                    <div class="panel-h">Assistance summary</div>
                    <dl class="divide-y divide-gov-border text-sm">
                        <div class="px-4 py-3"><dt class="text-xs font-bold uppercase text-gov-muted">Amount</dt><dd class="text-xl font-bold text-gov-dark">{{ program.amount_display }}</dd></div>
                        <div class="px-4 py-3"><dt class="text-xs font-bold uppercase text-gov-muted">Beneficiary</dt><dd>{{ program.beneficiary_label }}</dd></div>
                        <div class="px-4 py-3"><dt class="text-xs font-bold uppercase text-gov-muted">Availability</dt><dd>{{ program.availability_label }}</dd></div>
                        <div class="px-4 py-3"><dt class="text-xs font-bold uppercase text-gov-muted">Application window</dt><dd>{{ program.open_from }} — {{ program.open_until }}</dd></div>
                    </dl>
                    <div class="p-4">
                        <Link v-if="program.is_currently_open" class="btn-primary w-full text-center" :href="route('site.apply.create', program.slug)">Apply for this program</Link>
                        <p v-else class="text-sm text-gov-muted">This program is not open for application.</p>
                    </div>
                </div>
                <div class="panel panel-body text-sm">
                    Application forms change automatically based on the selected program. Administrators can add new programs, requirements, and questions without changing the system source code.
                </div>
            </aside>
        </div>
    </PublicLayout>
</template>
