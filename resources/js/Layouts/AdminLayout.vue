<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import SidebarBrand from '@/Components/SidebarBrand.vue';
import SidebarFooter from '@/Components/SidebarFooter.vue';
import Toaster from '@/Components/Toaster.vue';
import { useCan } from '@/composables/useCan';

const page = usePage();
const gov = computed(() => page.props.gov);
const clock = computed(() => page.props.clock);
const { can, user } = useCan();
const logout = useForm({});
const signOut = () => logout.post(route('logout'));

const active = (name, query = {}) => {
    if (query.status) {
        return route().current('admin.applications.index') && page.url.includes(`status=${query.status}`);
    }
    if (query.type) {
        return route().current('admin.applicants.index') && page.url.includes(`type=${query.type}`);
    }
    return route().current(name) || route().current(`${name.replace('.index', '')}.*`);
};
</script>

<template>
    <div class="admin-shell flex h-dvh flex-col overflow-hidden bg-gov-off">
        <Toaster />
        <a class="skip-link" href="#main">Skip to main content</a>
        <div class="official-strip no-print shrink-0" aria-hidden="true"><span /><span /><span /></div>
        <div class="flex min-h-0 flex-1">
            <aside class="no-print hidden w-60 shrink-0 flex-col overflow-hidden bg-gov-blue text-white lg:flex">
                <div class="shrink-0 border-b border-white/15">
                    <SidebarBrand />
                </div>
                <nav class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain pb-4" aria-label="Administration">
                    <Link v-if="can('dashboard.view')" class="sidebar-link" :class="{ active: route().current('admin.dashboard') }" :href="route('admin.dashboard')">Dashboard</Link>

                    <template v-if="can('applications.view')">
                        <p class="sidebar-group">Application Management</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.applications.*') && !$page.url.includes('status=') }" :href="route('admin.applications.index')">All Applications</Link>
                        <Link
                            class="sidebar-link"
                            :class="{ active: route().current('admin.workflow.*') || route().current('admin.verification.*') || route().current('admin.evaluation.*') || route().current('admin.approvals.*') || active('admin.applications.index', { status: 'rejected' }) || active('admin.applications.index', { status: 'for_revision' }) }"
                            :href="route('admin.workflow.index')"
                        >Workflow</Link>
                    </template>

                    <template v-if="can('programs.view')">
                        <p class="sidebar-group">Program Management</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.programs.*') }" :href="route('admin.programs.index')">Assistance Programs</Link>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.categories.*') }" :href="route('admin.categories.index')">Categories</Link>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.requirements.*') }" :href="route('admin.requirements.index')">Requirements</Link>
                    </template>

                    <template v-if="can('applicants.view')">
                        <p class="sidebar-group">Beneficiaries</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.applicants.*') && !$page.url.includes('type=') }" :href="route('admin.applicants.index')">Applicants</Link>
                        <Link class="sidebar-link" :class="{ active: active('admin.applicants.index', { type: 'student' }) }" :href="route('admin.applicants.index', { type: 'student' })">Students</Link>
                        <Link class="sidebar-link" :class="{ active: active('admin.applicants.index', { type: 'non_student' }) }" :href="route('admin.applicants.index', { type: 'non_student' })">Non-Students</Link>
                    </template>

                    <template v-if="can('releases.view')">
                        <p class="sidebar-group">Release Management</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.releases.index') }" :href="route('admin.releases.index')">Release Schedule</Link>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.releases.released') }" :href="route('admin.releases.released')">Released Assistance</Link>
                        <Link v-if="can('releases.verify')" class="sidebar-link" :class="{ active: route().current('admin.releases.verify*') || route().current('admin.releases.verify') }" :href="route('admin.releases.verify')">Claim Verification</Link>
                    </template>

                    <template v-if="can('reports.view')">
                        <p class="sidebar-group">Reports</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.reports.applications') }" :href="route('admin.reports.applications')">Application Reports</Link>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.reports.financial') }" :href="route('admin.reports.financial')">Financial Reports</Link>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.reports.beneficiaries') }" :href="route('admin.reports.beneficiaries')">Demographic Reports</Link>
                    </template>

                    <template v-if="can('announcements.view')">
                        <p class="sidebar-group">Communication</p>
                        <Link class="sidebar-link" :class="{ active: route().current('admin.announcements.*') }" :href="route('admin.announcements.index')">Announcements</Link>
                    </template>

                    <template v-if="can('users.view') || can('audit.view') || can('settings.view')">
                        <p class="sidebar-group">System</p>
                        <Link v-if="can('users.view')" class="sidebar-link" :class="{ active: route().current('admin.users.*') }" :href="route('admin.users.index')">Users</Link>
                        <Link v-if="can('roles.manage')" class="sidebar-link" :class="{ active: route().current('admin.roles.*') }" :href="route('admin.roles.index')">Roles & Permissions</Link>
                        <Link v-if="can('audit.view')" class="sidebar-link" :class="{ active: route().current('admin.audit-logs.*') }" :href="route('admin.audit-logs.index')">Audit Logs</Link>
                        <Link v-if="can('settings.view')" class="sidebar-link" :class="{ active: route().current('admin.settings.*') }" :href="route('admin.settings.edit')">System Settings</Link>
                    </template>
                </nav>
                <SidebarFooter :name="user?.name" :role="user?.role_name" :detail="user?.email" />
            </aside>
            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header class="no-print shrink-0 border-b-2 border-gov-gold bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest text-gov-blue">{{ gov.agency }}</p>
                            <p class="text-sm text-gov-muted">{{ gov.system_name }} &nbsp;|&nbsp; {{ clock?.datetime }}</p>
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
                    <nav class="flex gap-2 overflow-x-auto border-t border-gov-border px-2 py-2 text-xs lg:hidden">
                        <Link :href="route('admin.dashboard')">Dashboard</Link>
                        <Link :href="route('admin.applications.index')">Applications</Link>
                        <Link v-if="can('applications.view')" :href="route('admin.workflow.index')">Workflow</Link>
                        <Link :href="route('admin.programs.index')">Programs</Link>
                        <Link :href="route('admin.releases.index')">Releases</Link>
                    </nav>
                </header>
                <main id="main" class="custom-scroll min-h-0 flex-1 overflow-y-scroll overscroll-contain p-4 md:p-6" scroll-region>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
