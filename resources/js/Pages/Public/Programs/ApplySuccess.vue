<script setup>
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    result: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <PublicLayout>
        <Head title="Application received" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">Application received</p>
                <h1 class="mt-1">Thank you for applying!</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#d7e6f7]">
                    Your application for <strong class="text-white">{{ result.program_name }}</strong> has been recorded.
                </p>
            </div>
        </div>
        <div class="mx-auto max-w-3xl px-4 py-6 md:py-8">
            <div class="panel">
                <div class="panel-h">Application received</div>
                <div class="panel-body space-y-5 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gov-success/10 text-gov-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <p class="text-sm text-gov-text">
                        Your application number is:
                    </p>
                    <p class="text-3xl font-extrabold tracking-wide text-gov-dark">{{ result.application_no }}</p>

                    <div class="mx-auto max-w-xl border border-gov-border bg-gov-off px-4 py-3 text-left text-sm text-gov-text">
                        <p class="mb-2 font-bold uppercase tracking-wide text-gov-dark">What happens next</p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>
                                Your application number <strong>{{ result.application_no }}</strong> was sent to
                                <strong>{{ result.email }}</strong> together with your sign-in details
                                (email and temporary password).
                            </li>
                            <li>Please check your inbox (and spam folder) for the confirmation email.</li>
                            <li>Sign in to the applicant portal to upload the required documents, review, and formally submit your application to the office.</li>
                        </ul>
                    </div>

                    <div class="flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <Link class="btn-primary w-full sm:w-auto" :href="route('login')">Sign in to the applicant portal</Link>
                        <Link class="btn-ghost w-full sm:w-auto" :href="route('site.programs.index')">Back to programs</Link>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
