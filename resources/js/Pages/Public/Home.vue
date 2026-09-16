<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const page = usePage();
const gov = computed(() => page.props.gov);
const auth = computed(() => page.props.auth?.user);

defineProps({
    programs: { type: Object, required: true },
    announcements: { type: Array, default: () => [] },
    quick: { type: Array, default: () => [] },
});

const applyHref = computed(() => (auth.value ? route('applicant.programs.index') : route('register')));
</script>

<template>
    <PublicLayout>
        <Head title="Cash Assistance and Social Support Services" />
        <section class="relative isolate overflow-hidden">
            <img
                src="/images/nabua.jpg"
                alt="Nabua Local Government Center, Camarines Sur"
                class="absolute inset-0 h-full w-full object-cover object-[center_32%] sm:object-[center_42%]"
            >
            <div class="absolute inset-0 bg-gradient-to-b from-gov-navy/80 via-gov-blue/70 to-gov-navy/85 sm:bg-gradient-to-r sm:from-gov-navy/92 sm:via-gov-blue/75 sm:to-gov-navy/35" />
            <div class="absolute inset-0 hidden bg-gradient-to-t from-gov-navy/80 via-transparent to-gov-navy/25 sm:block" />

            <div class="relative mx-auto flex max-w-7xl flex-col justify-end px-4 py-10 text-white sm:min-h-[28rem] sm:py-14 md:min-h-[36rem] md:py-16">
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#cfe3f8] sm:text-[11px]">{{ gov.agency_short }} Official Services Portal</p>
                <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-white sm:text-xs">Nabua, Camarines Sur</p>
                <h1 class="mt-4 max-w-3xl text-[1.7rem] font-extrabold leading-tight sm:text-3xl md:text-5xl">Cash Assistance and Social Support Services</h1>
                <p class="mt-3 max-w-2xl text-sm text-[#d7e6f7] sm:mt-4 sm:text-base md:text-lg">Citizens, students, and qualified beneficiaries of the Municipality of Nabua, Camarines Sur may apply online for available educational, medical, emergency, livelihood, and other social assistance programs.</p>
                <div class="mt-6 flex flex-col gap-3 sm:mt-8 sm:flex-row sm:flex-wrap">
                    <Link :href="applyHref" class="btn-primary w-full border-white bg-white text-center text-gov-blue hover:bg-gov-light sm:w-auto">Apply for Assistance</Link>
                    <Link :href="route('site.programs.index')" class="btn-secondary w-full border-white bg-transparent text-center text-white hover:bg-white/10 sm:w-auto">View Available Programs</Link>
                </div>
                <dl class="mt-8 grid gap-px bg-white/20 sm:mt-10 sm:grid-cols-3">
                    <div class="bg-gov-navy/80 p-3 backdrop-blur-sm sm:p-4">
                        <dt class="text-[10px] uppercase tracking-wider text-[#9ec2ea]">Office hours</dt>
                        <dd class="mt-1 text-sm font-bold">8:00 AM – 5:00 PM</dd>
                    </div>
                    <div class="bg-gov-navy/80 p-3 backdrop-blur-sm sm:p-4">
                        <dt class="text-[10px] uppercase tracking-wider text-[#9ec2ea]">Hotline</dt>
                        <dd class="mt-1 text-sm font-bold">{{ gov.phone }}</dd>
                    </div>
                    <div class="bg-gov-navy/80 p-3 backdrop-blur-sm sm:p-4">
                        <dt class="text-[10px] uppercase tracking-wider text-[#9ec2ea]">Service fee</dt>
                        <dd class="mt-1 text-sm font-bold">Free of charge</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="border-b border-gov-border bg-white">
            <div class="mx-auto grid max-w-7xl md:grid-cols-3">
                <div class="border-b border-gov-border px-4 py-5 md:border-b-0 md:border-r">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gov-blue">01 — Filing</p>
                    <p class="mt-1 text-sm text-gov-text">Application numbers are issued for every filing.</p>
                </div>
                <div class="border-b border-gov-border px-4 py-5 md:border-b-0 md:border-r">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gov-blue">02 — Verification</p>
                    <p class="mt-1 text-sm text-gov-text">Documents are verified before evaluation and approval.</p>
                </div>
                <div class="px-4 py-5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gov-blue">03 — Release</p>
                    <p class="mt-1 text-sm text-gov-text">Releases are scheduled, recorded, and subject to claim verification.</p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 md:py-10">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="section-kicker">Quick access</p>
                    <h2 class="page-title text-xl sm:text-2xl">Assistance categories</h2>
                </div>
                <Link :href="route('site.programs.index')" class="btn-secondary btn-sm w-full text-center sm:w-auto">Program directory</Link>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <article v-for="item in quick" :key="item.title" class="panel flex flex-col">
                    <div class="panel-h">{{ item.title }}</div>
                    <div class="panel-body flex flex-1 flex-col">
                        <p class="flex-1 text-sm text-gov-text">{{ item.text }}</p>
                        <Link v-if="item.slug" :href="route('site.programs.show', item.slug)" class="btn-primary btn-sm mt-4 w-full text-center sm:w-auto">View Program</Link>
                    </div>
                </article>
            </div>
        </section>

        <section class="border-y border-gov-border bg-white">
            <div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
                <p class="section-kicker">Directory</p>
                <h2 class="page-title mb-4 text-xl sm:text-2xl">Available assistance programs</h2>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Category</th>
                                <th>Beneficiary</th>
                                <th>Amount</th>
                                <th>Availability</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="program in programs.data" :key="program.id">
                                <td data-label="Program" class="font-semibold">{{ program.name }}<div class="text-xs text-gov-muted">{{ program.code }}</div></td>
                                <td data-label="Category">{{ program.category?.name }}</td>
                                <td data-label="Beneficiary">{{ program.beneficiary_label }}</td>
                                <td data-label="Amount">{{ program.amount_display }}</td>
                                <td data-label="Availability">
                                    <StatusBadge :label="program.availability_label" :tone="program.is_currently_open ? 'success' : 'neutral'" />
                                </td>
                                <td data-label="Action"><Link :href="route('site.programs.show', program.slug)">Open</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :paginator="programs" />
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 md:py-10">
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="panel lg:col-span-2">
                    <div class="panel-h">Announcements and official notices</div>
                    <div class="divide-y divide-gov-border">
                        <Link
                            v-for="item in announcements"
                            :key="item.id"
                            :href="route('site.announcements.show', item.id)"
                            class="block px-4 py-3 text-gov-text no-underline hover:bg-gov-light"
                        >
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gov-blue">{{ item.type_label }} · {{ item.published_at }}</p>
                            <p class="font-bold">{{ item.title }}</p>
                            <p class="text-sm text-gov-muted">{{ item.excerpt }}</p>
                        </Link>
                        <p v-if="!announcements.length" class="px-4 py-6 text-sm text-gov-muted">No published announcements at this time.</p>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-h">Official reminder</div>
                    <div class="panel-body space-y-3 text-sm">
                        <p>All MSWDO cash assistance services are <strong>free of charge</strong>. Do not deal with fixers.</p>
                        <p>Keep your application number. Use <Link :href="route('site.status')">Check Application Status</Link> to track processing.</p>
                        <p class="break-words">For inquiries: {{ gov.email }} · {{ gov.phone }}</p>
                        <Link :href="route('site.how-to-apply')" class="btn-secondary w-full">How to apply</Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
