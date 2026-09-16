<script setup>
import { route } from 'ziggy-js';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    applicant: { type: Object, required: true },
    applications: { type: Object, required: true },
    counts: { type: Object, required: true },
    requirementTask: { type: Object, default: null },
});
</script>

<template>
    <ApplicantLayout>
        <Head title="Applicant Dashboard" />
        <PageHeader title="Applicant Dashboard" kicker="Beneficiary portal" :document-no="applicant.applicant_no" />

        <div v-if="requirementTask" class="panel mb-4">
            <div class="panel-h">Requirements to complete</div>
            <div class="panel-body space-y-3">
                <p class="mb-3 break-words text-sm">
                    Application <strong>{{ requirementTask.application_no }}</strong> for
                    <strong>{{ requirementTask.program_name }}</strong>
                    <template v-if="requirementTask.needs_revision">
                        needs a replacement for the document{{ requirementTask.revisions.length === 1 ? '' : 's' }} marked for revision.
                    </template>
                    <template v-else-if="requirementTask.needs_form">
                        still needs the program application form completed.
                    </template>
                    <template v-else-if="requirementTask.ready_to_submit">
                        has the required files. Review and submit it to the office.
                    </template>
                    <template v-else>
                        still needs the following required documents.
                    </template>
                </p>
                <ul v-if="requirementTask.revisions?.length" class="list-disc space-y-1 pl-5 text-sm">
                    <li v-for="item in requirementTask.revisions" :key="item.id" class="break-words">
                        <strong>{{ item.name }}</strong>
                        <span v-if="item.status_label" class="text-gov-warning"> — {{ item.status_label }}</span>
                        <span v-if="item.description" class="text-gov-muted"> — {{ item.description }}</span>
                    </li>
                </ul>
                <ul v-if="requirementTask.missing.length" class="list-disc space-y-1 pl-5 text-sm">
                    <li v-for="item in requirementTask.missing" :key="item.id" class="break-words">
                        <strong>{{ item.name }}</strong>
                        <span v-if="item.description" class="text-gov-muted"> — {{ item.description }}</span>
                    </li>
                </ul>
                <Link class="btn-primary btn-sm w-full text-center sm:w-auto" :href="requirementTask.continue_path">
                    {{ requirementTask.needs_revision ? 'Replace documents' : (requirementTask.needs_form ? 'Complete application form' : (requirementTask.ready_to_submit ? 'Review and submit' : 'Upload requirements')) }}
                </Link>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4">
            <div class="stat-card">
                <div class="label">Total</div>
                <div class="value">{{ counts.total }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Pending</div>
                <div class="value">{{ counts.pending }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Approved</div>
                <div class="value">{{ counts.approved }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Completed</div>
                <div class="value">{{ counts.completed }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="panel order-2 lg:order-1 lg:col-span-1">
                <div class="panel-h">Applicant information</div>
                <dl class="divide-y divide-gov-border text-sm">
                    <div class="px-4 py-2">
                        <dt class="text-xs font-bold uppercase text-gov-muted">Name</dt>
                        <dd class="break-words">{{ applicant.full_name }}</dd>
                    </div>
                    <div class="px-4 py-2">
                        <dt class="text-xs font-bold uppercase text-gov-muted">Beneficiary type</dt>
                        <dd>{{ applicant.beneficiary_label }}</dd>
                    </div>
                    <div class="px-4 py-2">
                        <dt class="text-xs font-bold uppercase text-gov-muted">Address</dt>
                        <dd class="break-words">{{ applicant.full_address }}</dd>
                    </div>
                    <div class="px-4 py-2">
                        <dt class="text-xs font-bold uppercase text-gov-muted">Contact</dt>
                        <dd class="break-words">{{ applicant.contact_number }}</dd>
                    </div>
                    <div class="px-4 py-2">
                        <dt class="text-xs font-bold uppercase text-gov-muted">Email</dt>
                        <dd class="break-all">{{ applicant.email }}</dd>
                    </div>
                </dl>
                <div class="p-4">
                    <Link class="btn-secondary btn-sm w-full text-center sm:w-auto" :href="route('applicant.profile.edit')">Update profile</Link>
                </div>
            </div>

            <div class="panel order-1 lg:order-2 lg:col-span-2">
                <div class="panel-h">Assistance history</div>
                <div v-if="applications.data.length" class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Application no.</th>
                                <th>Program</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="application in applications.data" :key="application.id">
                                <td data-label="No.">{{ application.application_no }}</td>
                                <td data-label="Program">{{ application.program?.name }}</td>
                                <td data-label="Date">{{ application.submitted_at }}</td>
                                <td data-label="Status"><StatusBadge :label="application.status_label" :tone="application.status_tone" /></td>
                                <td data-label="Action">
                                    <Link class="font-semibold" :href="route('applicant.applications.show', application.id)">Open</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="applications.data.length" class="px-4 pb-4"><Pagination :paginator="applications" /></div>
                <p v-else class="px-4 py-3 text-sm text-gov-muted">
                    No applications yet.
                    <Link :href="route('applicant.programs.index')">View available programs</Link>.
                </p>
            </div>
        </div>
    </ApplicantLayout>
</template>
