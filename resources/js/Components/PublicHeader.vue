<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Seal from '@/Components/Seal.vue';

const page = usePage();
const gov = computed(() => page.props.gov);
const auth = computed(() => page.props.auth?.user);
const clock = computed(() => page.props.clock);
const logout = useForm({});
const menuOpen = ref(false);
const confirmSignOut = ref(false);
const askSignOut = () => {
    menuOpen.value = false;
    confirmSignOut.value = true;
};
const cancelSignOut = () => {
    if (logout.processing) return;
    confirmSignOut.value = false;
};

const nav = [
    ['Home', 'site.home'],
    ['Programs', 'site.programs.index'],
    ['How to Apply', 'site.how-to-apply'],
    ['Requirements', 'site.requirements'],
    ['Announcements', 'site.announcements.index'],
    ['Contact', 'site.contact'],
    ['Track Status', 'site.status'],
];

const isActive = (name) => route().current(name) || route().current(`${name}.*`) || route().current(name.replace('.index', '.*'));

const signOut = () => logout.post(route('logout'));

watch(() => page.url, () => {
    menuOpen.value = false;
});
</script>

<template>
    <div class="overflow-x-hidden border-b border-gov-border bg-gov-off">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-1.5 text-[11px] text-gov-muted">
            <p class="min-w-0 truncate">Official website of {{ gov.lgu }}</p>
            <p class="hidden shrink-0 sm:block">{{ clock?.date }}</p>
        </div>
    </div>

    <header class="sticky top-0 z-50 overflow-x-hidden border-b border-gov-border bg-white/95 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2.5 md:py-3">
            <Link :href="route('site.home')" class="flex min-w-0 items-center gap-2.5 text-gov-dark no-underline hover:text-gov-dark hover:no-underline sm:gap-3">
                <Seal class="h-12 w-12 shrink-0 md:h-14 md:w-14" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-extrabold leading-tight text-gov-dark sm:text-base md:text-lg">{{ gov.agency_short }}</span>
                    <span class="block truncate text-[11px] text-gov-muted sm:text-xs">{{ gov.lgu }}</span>
                </span>
            </Link>

            <nav class="gov-header-nav hidden min-w-0 items-center lg:flex" aria-label="Primary">
                <Link
                    v-for="[label, name] in nav"
                    :key="name"
                    :href="route(name)"
                    :class="{ active: isActive(name) }"
                >{{ label }}</Link>
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <div class="hidden items-center gap-2 lg:flex">
                    <template v-if="auth">
                        <Link v-if="auth.can_access_admin" :href="route('admin.dashboard')" class="btn-secondary btn-sm">Dashboard</Link>
                        <Link v-else-if="auth.is_applicant" :href="route('applicant.dashboard')" class="btn-secondary btn-sm">Applicant Portal</Link>
                        <button class="btn-ghost btn-sm" type="button" @click="askSignOut">Sign out</button>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="btn-primary btn-sm"
                            :class="{ 'border-gov-navy': route().current('login') }"
                        >Sign in</Link>
                    </template>
                </div>
                <button
                    class="inline-flex h-10 w-10 items-center justify-center border border-gov-border bg-white text-gov-dark hover:bg-gov-off lg:hidden"
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-nav"
                    :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
                    @click="menuOpen = !menuOpen"
                >
                    <svg v-if="!menuOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            v-show="menuOpen"
            id="mobile-nav"
            class="max-h-[min(28rem,calc(100dvh-5rem))] overflow-y-auto border-t border-gov-border bg-white lg:hidden"
        >
            <nav class="gov-header-nav mx-auto flex max-w-7xl flex-col px-2 py-2" aria-label="Mobile">
                <Link
                    v-for="[label, name] in nav"
                    :key="name"
                    :href="route(name)"
                    :class="{ active: isActive(name) }"
                >{{ label === 'Track Status' ? 'Check Application Status' : label }}</Link>
            </nav>
            <div class="flex flex-col gap-2 border-t border-gov-border px-4 py-3 sm:flex-row sm:flex-wrap">
                <template v-if="auth">
                    <Link v-if="auth.can_access_admin" :href="route('admin.dashboard')" class="btn-secondary btn-sm w-full sm:w-auto">Dashboard</Link>
                    <Link v-else-if="auth.is_applicant" :href="route('applicant.dashboard')" class="btn-secondary btn-sm w-full sm:w-auto">Applicant Portal</Link>
                    <button class="btn-ghost btn-sm w-full sm:w-auto" type="button" @click="askSignOut">Sign out</button>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="btn-primary btn-sm w-full sm:w-auto">Sign in</Link>
                </template>
            </div>
        </div>
    </header>
    <ConfirmModal
        :show="confirmSignOut"
        title="Sign out"
        message="Are you sure you want to sign out?"
        confirm-label="Yes, sign out"
        cancel-label="Stay signed in"
        :processing="logout.processing"
        @confirm="signOut"
        @cancel="cancelSignOut"
    />
</template>
