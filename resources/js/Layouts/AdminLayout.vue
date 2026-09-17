<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import SidebarBrand from '@/Components/SidebarBrand.vue';
import SidebarFooter from '@/Components/SidebarFooter.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import Toaster from '@/Components/Toaster.vue';
import { useCan } from '@/composables/useCan';
import { useSidebarCollapse } from '@/composables/useSidebarCollapse';

const ICONS = {
    dashboard: ['M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'M9 22V12h6v10'],
    applications: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M16 13H8', 'M16 17H8', 'M10 9H8'],
    workflow: ['M4 5h6v14H4z', 'M14 5h6v8h-6z'],
    programs: ['M20 12v10H4V12', 'M2 7h20v5H2z', 'M12 22V7', 'M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z', 'M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z'],
    categories: ['M12 2 2 7l10 5 10-5-10-5z', 'M2 17l10 5 10-5', 'M2 12l10 5 10-5'],
    requirements: ['M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2', 'M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2', 'M9 14l2 2 4-4'],
    applicants: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
    students: ['M22 10v6', 'M6 12v5c3 3 9 3 12 0v-5', 'M2 10l10-5 10 5-10 5z'],
    nonStudents: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    schedule: ['M8 2v4', 'M16 2v4', 'M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'],
    released: ['M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z', 'M3.3 7 12 12l8.7-5', 'M12 22V12'],
    verify: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', 'M9 12l2 2 4-4'],
    reports: ['M12 20V10', 'M18 20V4', 'M6 20v-4'],
    financial: ['M12 1v22', 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
    demographic: ['M21.21 15.89A10 10 0 1 1 8 2.83', 'M22 12A10 10 0 0 0 12 2v10z'],
    announcements: ['M3 11l19-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6'],
    users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    roles: ['M19 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2z', 'M7 11V7a5 5 0 0 1 10 0v4'],
    audit: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
    settings: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'],
};

const page = usePage();
const gov = computed(() => page.props.gov);
const clock = computed(() => page.props.clock);
const { can, user } = useCan();
const logout = useForm({});
const signOut = () => logout.post(route('logout'));
const { collapsed: sidebarCollapsed, toggle: toggleDesktopSidebar } = useSidebarCollapse('admin-sidebar-collapsed');
const mobileOpen = ref(false);
const desktop = ref(typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches);
let stopNavigate = null;

const compact = computed(() => desktop.value && sidebarCollapsed.value);
const sidebarVisible = computed(() => desktop.value || mobileOpen.value);

const updateDesktop = () => {
    desktop.value = window.matchMedia('(min-width: 1024px)').matches;

    if (desktop.value) {
        mobileOpen.value = false;
    }
};

const toggleSidebar = () => {
    if (desktop.value) {
        toggleDesktopSidebar();
        return;
    }

    mobileOpen.value = ! mobileOpen.value;
};

const closeMobileSidebar = () => {
    mobileOpen.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        closeMobileSidebar();
    }
};

onMounted(() => {
    updateDesktop();
    window.addEventListener('resize', updateDesktop);
    window.addEventListener('keydown', onKeydown);
    stopNavigate = router.on('navigate', closeMobileSidebar);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateDesktop);
    window.removeEventListener('keydown', onKeydown);
    stopNavigate?.();
});

const active = (name, query = {}) => {
    if (query.status) {
        return route().current('admin.applications.index') && page.url.includes(`status=${query.status}`);
    }
    if (query.type) {
        return route().current('admin.applicants.index') && page.url.includes(`type=${query.type}`);
    }
    return route().current(name) || route().current(`${name.replace('.index', '')}.*`);
};

const navGroups = computed(() => {
    void page.url;

    return [
        {
            show: can('dashboard.view'),
            items: [
                { label: 'Dashboard', href: route('admin.dashboard'), active: route().current('admin.dashboard'), icon: ICONS.dashboard },
            ],
        },
        {
            show: can('applications.view'),
            label: 'Application Management',
            items: [
                {
                    label: 'All Applications',
                    href: route('admin.applications.index'),
                    active: route().current('admin.applications.*') && ! page.url.includes('status='),
                    icon: ICONS.applications,
                },
                {
                    label: 'Workflow',
                    href: route('admin.workflow.index'),
                    active: route().current('admin.workflow.*')
                        || route().current('admin.verification.*')
                        || route().current('admin.evaluation.*')
                        || route().current('admin.approvals.*')
                        || active('admin.applications.index', { status: 'rejected' })
                        || active('admin.applications.index', { status: 'for_revision' }),
                    icon: ICONS.workflow,
                },
            ],
        },
        {
            show: can('programs.view'),
            label: 'Program Management',
            items: [
                { label: 'Assistance Programs', href: route('admin.programs.index'), active: route().current('admin.programs.*'), icon: ICONS.programs },
                { label: 'Categories', href: route('admin.categories.index'), active: route().current('admin.categories.*'), icon: ICONS.categories },
                { label: 'Requirements', href: route('admin.requirements.index'), active: route().current('admin.requirements.*'), icon: ICONS.requirements },
            ],
        },
        {
            show: can('applicants.view'),
            label: 'Beneficiaries',
            items: [
                { label: 'Applicants', href: route('admin.applicants.index'), active: route().current('admin.applicants.*') && ! page.url.includes('type='), icon: ICONS.applicants },
                { label: 'Students', href: route('admin.applicants.index', { type: 'student' }), active: active('admin.applicants.index', { type: 'student' }), icon: ICONS.students },
                { label: 'Non-Students', href: route('admin.applicants.index', { type: 'non_student' }), active: active('admin.applicants.index', { type: 'non_student' }), icon: ICONS.nonStudents },
            ],
        },
        {
            show: can('releases.view'),
            label: 'Release Management',
            items: [
                { label: 'Release Schedule', href: route('admin.releases.index'), active: route().current('admin.releases.index'), icon: ICONS.schedule },
                { label: 'Released Assistance', href: route('admin.releases.released'), active: route().current('admin.releases.released'), icon: ICONS.released },
                ...(can('releases.verify') ? [{
                    label: 'Claim Verification',
                    href: route('admin.releases.verify'),
                    active: route().current('admin.releases.verify*') || route().current('admin.releases.verify'),
                    icon: ICONS.verify,
                }] : []),
            ],
        },
        {
            show: can('reports.view'),
            label: 'Reports',
            items: [
                { label: 'Application Reports', href: route('admin.reports.applications'), active: route().current('admin.reports.applications'), icon: ICONS.reports },
                { label: 'Financial Reports', href: route('admin.reports.financial'), active: route().current('admin.reports.financial'), icon: ICONS.financial },
                { label: 'Demographic Reports', href: route('admin.reports.beneficiaries'), active: route().current('admin.reports.beneficiaries'), icon: ICONS.demographic },
            ],
        },
        {
            show: can('announcements.view'),
            label: 'Communication',
            items: [
                { label: 'Announcements', href: route('admin.announcements.index'), active: route().current('admin.announcements.*'), icon: ICONS.announcements },
            ],
        },
        {
            show: can('users.view') || can('audit.view') || can('settings.view'),
            label: 'System',
            items: [
                ...(can('users.view') ? [{ label: 'Users', href: route('admin.users.index'), active: route().current('admin.users.*'), icon: ICONS.users }] : []),
                ...(can('roles.manage') ? [{ label: 'Roles & Permissions', href: route('admin.roles.index'), active: route().current('admin.roles.*'), icon: ICONS.roles }] : []),
                ...(can('audit.view') ? [{ label: 'Audit Logs', href: route('admin.audit-logs.index'), active: route().current('admin.audit-logs.*'), icon: ICONS.audit }] : []),
                ...(can('settings.view') ? [{ label: 'System Settings', href: route('admin.settings.edit'), active: route().current('admin.settings.*'), icon: ICONS.settings }] : []),
            ],
        },
    ].filter((group) => group.show && group.items.length);
});
</script>

<template>
    <div class="admin-shell flex h-dvh flex-col overflow-hidden bg-gov-off">
        <Toaster />
        <a class="skip-link" href="#main">Skip to main content</a>
        <div class="official-strip no-print shrink-0" aria-hidden="true"><span /><span /><span /></div>
        <div class="flex min-h-0 flex-1">
            <button
                v-if="mobileOpen && !desktop"
                type="button"
                class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                aria-label="Close side navigation"
                @click="closeMobileSidebar"
            />
            <aside
                id="admin-sidenav"
                class="no-print shrink-0 flex-col overflow-hidden bg-gov-blue text-white transition-[width] duration-200"
                :class="[
                    sidebarVisible ? 'flex' : 'hidden',
                    compact ? 'w-[4.5rem]' : 'w-60',
                    desktop ? '' : 'fixed inset-y-0 left-0 z-40',
                ]"
            >
                <div class="shrink-0 border-b border-white/15">
                    <SidebarBrand :compact="compact" />
                </div>
                <nav class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain pb-4" aria-label="Administration">
                    <template v-for="(group, groupIndex) in navGroups" :key="group.label || groupIndex">
                        <p v-if="group.label && !compact" class="sidebar-group">{{ group.label }}</p>
                        <div v-else-if="group.label && compact && groupIndex > 0" class="mx-3 my-2 h-px bg-white/15" aria-hidden="true" />
                        <SidebarNavLink
                            v-for="item in group.items"
                            :key="item.href + item.label"
                            :href="item.href"
                            :label="item.label"
                            :icon="item.icon"
                            :active="item.active"
                            :compact="compact"
                        />
                    </template>
                </nav>
                <SidebarFooter :name="user?.name" :role="user?.role_name" :detail="user?.email" :compact="compact" />
            </aside>
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header class="no-print shrink-0 border-b-2 border-gov-gold bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center border border-gov-border text-gov-blue hover:bg-gov-off"
                                :aria-expanded="desktop ? !sidebarCollapsed : mobileOpen"
                                aria-controls="admin-sidenav"
                                :aria-label="compact || (!desktop && !mobileOpen) ? 'Expand side navigation' : 'Collapse side navigation'"
                                :title="compact || (!desktop && !mobileOpen) ? 'Expand side navigation' : 'Collapse side navigation'"
                                @click="toggleSidebar"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="16" rx="1" />
                                    <path d="M9 4v16" />
                                    <path v-if="compact || (!desktop && !mobileOpen)" d="M14 9l3 3-3 3" />
                                    <path v-else d="M16 9l-3 3 3 3" />
                                </svg>
                            </button>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-gov-blue">{{ gov.agency }}</p>
                                <p class="text-sm text-gov-muted">{{ gov.system_name }} &nbsp;|&nbsp; {{ clock?.datetime }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 text-right">
                                <p class="truncate text-sm font-semibold leading-tight text-gov-blue">{{ user?.name }}</p>
                                <p class="truncate text-xs uppercase tracking-wide text-gov-muted">{{ user?.role_name }}</p>
                            </div>
                            <Link :href="route('site.home')" class="btn-ghost btn-sm">Public site</Link>
                            <button class="btn-primary btn-sm" type="button" @click="signOut">Sign out</button>
                        </div>
                    </div>
                    <nav v-if="!mobileOpen" class="flex gap-2 overflow-x-auto border-t border-gov-border px-2 py-2 text-xs lg:hidden">
                        <Link v-if="can('dashboard.view')" :href="route('admin.dashboard')">Dashboard</Link>
                        <Link v-if="can('applications.view')" :href="route('admin.applications.index')">Applications</Link>
                        <Link v-if="can('applications.view')" :href="route('admin.workflow.index')">Workflow</Link>
                        <Link v-if="can('programs.view')" :href="route('admin.programs.index')">Programs</Link>
                        <Link v-if="can('releases.view')" :href="route('admin.releases.index')">Releases</Link>
                    </nav>
                </header>
                <main id="main" class="custom-scroll min-h-0 flex-1 overflow-y-scroll overscroll-contain p-4 md:p-6" scroll-region>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
