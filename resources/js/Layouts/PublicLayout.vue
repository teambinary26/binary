<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Seal from '@/Components/Seal.vue';
import Toaster from '@/Components/Toaster.vue';
import PublicHeader from '@/Components/PublicHeader.vue';

const page = usePage();
const gov = computed(() => page.props.gov);
const clock = computed(() => page.props.clock);
</script>

<template>
    <div class="min-h-screen overflow-x-hidden">
        <Toaster />
        <a class="skip-link" href="#main">Skip to main content</a>
        <div class="official-strip" aria-hidden="true"><span /><span /><span /></div>
        <PublicHeader />

        <main id="main">
            <slot />
        </main>

        <footer class="mt-8 border-t-4 border-gov-blue bg-gov-dark text-white md:mt-10">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 md:grid-cols-4 md:py-10">
                <div class="md:col-span-2">
                    <div class="flex items-start gap-3">
                        <Seal class="h-14 w-14 shrink-0 md:h-16 md:w-16" />
                        <div class="min-w-0">
                            <p class="text-xs uppercase tracking-widest text-[#9ec2ea]">{{ gov.republic }}</p>
                            <p class="text-base font-bold md:text-lg">{{ gov.agency }}</p>
                            <p class="text-sm text-[#cfe3f8]">{{ gov.lgu }}, {{ gov.province }}</p>
                        </div>
                    </div>
                    <p class="mt-4 max-w-xl text-sm text-[#cfe3f8]">This is an official information system of the Municipal Government of Nabua, Camarines Sur. All cash assistance applications are processed according to established guidelines, documentary requirements, and audit controls.</p>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide">Office</h2>
                    <ul class="mt-3 space-y-1 break-words text-sm text-[#cfe3f8]">
                        <li>{{ gov.address }}</li>
                        <li>Tel. {{ gov.phone }}</li>
                        <li>{{ gov.email }}</li>
                        <li>{{ gov.office_hours }}</li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide">Transparency</h2>
                    <ul class="mt-3 space-y-1 text-sm">
                        <li><Link class="text-[#cfe3f8] hover:text-white" :href="route('site.how-to-apply')">How to apply</Link></li>
                        <li><Link class="text-[#cfe3f8] hover:text-white" :href="route('site.requirements')">Documentary requirements</Link></li>
                        <li><Link class="text-[#cfe3f8] hover:text-white" :href="route('site.announcements.index')">Public announcements</Link></li>
                        <li><Link class="text-[#cfe3f8] hover:text-white" :href="route('site.status')">Track an application</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10 px-4 py-3 text-center text-xs text-[#9ec2ea]">
                © {{ clock?.year }} {{ gov.lgu }} — {{ gov.system_name }}. All rights reserved. Services are free of charge.
            </div>
        </footer>
    </div>
</template>
