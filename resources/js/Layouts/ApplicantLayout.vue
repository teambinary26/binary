<script setup>
import { computed, h } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Toaster from '@/Components/Toaster.vue';
import SidebarBrand from '@/Components/SidebarBrand.vue';
import SidebarFooter from '@/Components/SidebarFooter.vue';

const page = usePage();
const gov = computed(() => page.props.gov);
const user = computed(() => page.props.auth?.user);
const unread = computed(() => page.props.unreadNotificationCount ?? 0);
const logout = useForm({});

const NavIcon = (props) => h(
    'svg',
    {
        class: 'h-5 w-5',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': '2',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'aria-hidden': 'true',
    },
    props.paths.map((d) => h('path', { d })),
);
NavIcon.props = ['paths'];

const links = [
    {
        label: 'Dashboard',
        short: 'Home',
        name: 'applicant.dashboard',
        icon: ['M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'],
    },
    {
        label: 'Available Programs',
        short: 'Programs',
        name: 'applicant.programs.index',
        icon: ['M8 6h13', 'M8 12h13', 'M8 18h13', 'M3 6h.01', 'M3 12h.01', 'M3 18h.01'],
    },
    {
        label: 'My Applications',
        short: 'Apps',
        name: 'applicant.applications.index',
        icon: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
    },
    {
        label: 'Notifications',
        short: 'Alerts',
        name: 'applicant.notifications.index',
        icon: ['M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5', 'M9 17a3 3 0 0 0 6 0'],
    },
    {
        label: 'My Profile',
        short: 'Profile',
        name: 'applicant.profile.edit',
        icon: ['M20 21a8 8 0 0 0-16 0', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
    },
];

const isActive = (name) => {
    if (name === 'applicant.dashboard') {
        return route().current(name);
    }
    if (name === 'applicant.applications.index') {
        return route().current('applicant.applications.*') || route().current('applicant.apply.*');
    }
    return route().current(name) || route().current(name.replace('.index', '.*'));
};

const signOut = () => logout.post(route('logout'));
</script>

<template>
    <div class="applicant-shell flex h-dvh flex-col overflow-hidden bg-gov-off">
        <Toaster />
        <a class="skip-link" href="#main">Skip to main content</a>
        <div class="official-strip no-print shrink-0" aria-hidden="true"><span /><span /><span /></div>
        <div class="flex min-h-0 min-w-0 flex-1">
            <aside class="no-print hidden w-60 shrink-0 flex-col overflow-hidden bg-gov-blue text-white md:flex">
                <div class="shrink-0 border-b border-white/15">
                    <SidebarBrand />
                </div>
                <nav class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain py-3" aria-label="Applicant">
                    <Link
                        v-for="link in links"
                        :key="link.name"
                        class="sidebar-link"
                        :class="{ active: isActive(link.name) }"
                        :href="route(link.name)"
                    >{{ link.label }}</Link>
                    <Link class="sidebar-link" :href="route('site.home')">Public Website</Link>
                </nav>
                <SidebarFooter :name="user?.name" :role="user?.role_name" :detail="user?.applicant?.applicant_no" />
            </aside>
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header class="no-print shrink-0 border-b-2 border-gov-gold bg-white">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 sm:gap-3 sm:px-4 sm:py-3">
                        <div class="min-w-0">
                            <p class="truncate text-[10px] font-bold uppercase tracking-widest text-gov-blue sm:text-[11px]">{{ gov.agency_short }} — {{ gov.lgu }}</p>
                            <p class="truncate text-sm font-bold text-gov-blue sm:text-base">{{ user?.name }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
                            <Link
                                :href="route('site.home')"
                                class="btn-ghost btn-sm hidden no-underline sm:inline-flex md:hidden"
                            >
                                Website
                            </Link>
                            <Link
                                :href="route('site.home')"
                                class="inline-flex h-9 w-9 items-center justify-center border border-gov-border text-gov-blue no-underline hover:bg-gov-off hover:no-underline sm:hidden"
                                aria-label="Public website"
                                title="Public website"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                                </svg>
                            </Link>
                            <Link
                                :href="route('applicant.notifications.index')"
                                class="relative hidden text-sm font-semibold no-underline md:inline"
                            >
                                Notifications
                                <span v-if="unread > 0" class="ml-1 bg-gov-danger px-1.5 py-0.5 text-[10px] font-bold text-white">{{ unread }}</span>
                            </Link>
                            <button class="btn-primary btn-sm whitespace-nowrap" type="button" @click="signOut">Sign out</button>
                        </div>
                    </div>
                </header>
                <nav
                    class="no-print grid grid-cols-5 border-b border-gov-border bg-white md:hidden"
                    aria-label="Mobile applicant"
                >
                    <Link
                        v-for="link in links"
                        :key="link.name"
                        class="relative flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-1.5 no-underline"
                        :class="isActive(link.name) ? 'bg-gov-blue text-white' : 'text-gov-blue'"
                        :href="route(link.name)"
                        :aria-label="link.label"
                        :title="link.label"
                    >
                        <NavIcon :paths="link.icon" />
                        <span class="max-w-full truncate text-[9px] font-bold uppercase leading-none tracking-wide">{{ link.short }}</span>
                        <span
                            v-if="link.name === 'applicant.notifications.index' && unread > 0"
                            class="absolute right-1.5 top-1 min-w-4 bg-gov-danger px-1 text-center text-[10px] font-bold leading-4 text-white"
                        >{{ unread }}</span>
                    </Link>
                </nav>
                <main id="main" class="custom-scroll min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-scroll overscroll-contain p-3 sm:p-4 md:p-6" scroll-region>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
