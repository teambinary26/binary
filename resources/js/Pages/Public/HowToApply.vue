<script setup>
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const steps = [
    ['Select Program', 'Choose an open assistance program that matches your beneficiary type.'],
    ['Check Eligibility', 'Confirm that you meet residency and program-specific conditions.'],
    ['Application Form', 'Complete the application form for the selected program and verify your email.'],
    ['Upload Requirements', 'After the application is filed, submit the required documents in PDF, JPG, or PNG format.'],
    ['Review Application', 'Verify all information before filing the application with the office.'],
    ['Submit', 'An official application number is issued upon submission.'],
    ['Document Verification', 'MSWDO staff examine uploaded requirements.'],
    ['Evaluation', 'The application is assessed against eligibility rules.'],
    ['Approval / Rejection', 'An approving officer records the official decision.'],
    ['Release Scheduling', 'Approved assistance is scheduled for release.'],
    ['Assistance Release', 'The amount is released through the recorded method.'],
    ['Completed', 'Claim verification closes the application record.'],
];
</script>

<template>
    <PublicLayout>
        <Head title="How to Apply" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">Citizen guide</p>
                <h1 class="mt-1">How to Apply</h1>
            </div>
        </div>
        <div class="mx-auto max-w-7xl px-4 py-6 md:py-8">
            <ol class="grid gap-3 md:grid-cols-2">
                <li v-for="(step, index) in steps" :key="step[0]" class="panel flex min-w-0 gap-3 sm:gap-4">
                    <div class="flex w-12 shrink-0 items-center justify-center bg-gov-blue text-lg font-extrabold text-white sm:w-16 sm:text-xl">{{ index + 1 }}</div>
                    <div class="min-w-0 py-3 pr-3 sm:py-4 sm:pr-4">
                        <p class="font-bold text-gov-dark">{{ step[0] }}</p>
                        <p class="text-sm text-gov-muted">{{ step[1] }}</p>
                    </div>
                </li>
            </ol>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <Link class="btn-primary w-full text-center sm:w-auto" :href="route('register')">Create an applicant account</Link>
                <Link class="btn-secondary w-full text-center sm:w-auto" :href="route('site.programs.index')">Browse programs</Link>
            </div>
        </div>
    </PublicLayout>
</template>
