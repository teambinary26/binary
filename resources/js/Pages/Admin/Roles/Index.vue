<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
});

const roleOrder = { staff: 0, sk: 1 };

const editableRoles = computed(() => props.roles
    .filter((role) => role.can_edit)
    .slice()
    .sort((left, right) => (roleOrder[left.slug] ?? 9) - (roleOrder[right.slug] ?? 9)));

const fixedRoles = computed(() => props.roles.filter((role) => ! role.can_edit));

const forms = Object.fromEntries(
    props.roles
        .filter((role) => role.can_edit)
        .map((role) => [
            role.id,
            useForm({
                permission_slugs: (role.permissions ?? []).map((permission) => permission.slug),
            }),
        ]),
);

const formFor = (role) => forms[role.id];

const hasPermission = (role, slug) => formFor(role).permission_slugs.includes(slug);

const togglePermission = (role, permission) => {
    const form = formFor(role);

    if (permission.locked || permission.slug === 'dashboard.view') {
        return;
    }

    if (hasPermission(role, permission.slug)) {
        form.permission_slugs = form.permission_slugs.filter((slug) => slug !== permission.slug);
        return;
    }

    form.permission_slugs = [...form.permission_slugs, permission.slug];
};

const modulePermissions = (module) => (module.permissions ?? []).filter((permission) => ! permission.locked);

const moduleChecked = (role, module) => {
    const items = modulePermissions(module);

    return items.length > 0 && items.every((permission) => hasPermission(role, permission.slug));
};

const toggleModule = (role, module) => {
    const form = formFor(role);
    const items = modulePermissions(module);
    const slugs = items.map((permission) => permission.slug);

    if (moduleChecked(role, module)) {
        form.permission_slugs = form.permission_slugs.filter((slug) => ! slugs.includes(slug) || slug === 'dashboard.view');
        return;
    }

    form.permission_slugs = [...new Set([...form.permission_slugs, ...slugs])];
};

const saveAccess = (role) => {
    formFor(role).put(route('admin.roles.update', role.id), { preserveScroll: true });
};

const saveLabel = (role) => (role.slug === 'sk' ? 'Save SK access' : `Save ${role.name.toLowerCase()} access`);
</script>

<template>
    <AdminLayout>
        <Head title="Roles and Permissions" />
        <PageHeader title="Roles & Permissions" kicker="Choose which pages staff and Sangguniang Kabataan can open. Administrator access stays full." />

        <div v-for="role in editableRoles" :key="role.id" class="panel mb-4">
            <div class="panel-h">{{ role.name }} <span class="font-normal normal-case tracking-normal">({{ role.users_count }} users)</span></div>
            <form class="panel-body space-y-4 text-sm" @submit.prevent="saveAccess(role)">
                <p class="text-gov-muted">{{ role.description }} Tick the pages and actions this account may use. Dashboard stays available so they can sign in.</p>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section v-for="module in catalog" :key="`${role.id}-${module.module}`" class="border border-gov-border">
                        <div class="flex items-center justify-between border-b border-gov-border bg-gov-off px-3 py-2">
                            <h2 class="text-xs font-bold uppercase tracking-wide text-gov-dark">{{ module.module }}</h2>
                            <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gov-muted">
                                <input type="checkbox" :checked="moduleChecked(role, module)" @change="toggleModule(role, module)">
                                Select all
                            </label>
                        </div>
                        <ul class="divide-y divide-gov-border">
                            <li v-for="permission in module.permissions" :key="`${role.id}-${permission.slug}`" class="flex items-start gap-3 px-3 py-2">
                                <input
                                    :id="`perm-${role.id}-${permission.slug}`"
                                    type="checkbox"
                                    class="mt-0.5"
                                    :checked="hasPermission(role, permission.slug) || permission.slug === 'dashboard.view'"
                                    :disabled="permission.locked || permission.slug === 'dashboard.view'"
                                    @change="togglePermission(role, permission)"
                                >
                                <label :for="`perm-${role.id}-${permission.slug}`" class="min-w-0">
                                    <span class="font-semibold text-gov-dark">{{ permission.name }}</span>
                                    <span v-if="permission.locked" class="mt-0.5 block text-xs text-gov-muted">Administrators only</span>
                                    <span v-else-if="permission.slug === 'dashboard.view'" class="mt-0.5 block text-xs text-gov-muted">Required for sign-in</span>
                                </label>
                            </li>
                        </ul>
                    </section>
                </div>
                <p v-if="formFor(role).errors.role || formFor(role).errors.permission_slugs" class="field-error">
                    {{ formFor(role).errors.role || formFor(role).errors.permission_slugs }}
                </p>
                <div class="flex justify-end">
                    <button class="btn-primary" type="submit" :disabled="formFor(role).processing">{{ saveLabel(role) }}</button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div v-for="role in fixedRoles" :key="role.id" class="panel">
                <div class="panel-h">{{ role.name }} <span class="font-normal normal-case tracking-normal">({{ role.users_count }} users)</span></div>
                <div class="panel-body text-sm">
                    <p class="mb-2 text-gov-muted">{{ role.description }}</p>
                    <div class="flex flex-wrap gap-1">
                        <span v-for="permission in role.permissions" :key="permission.id" class="badge badge-info">{{ permission.name }}</span>
                        <span v-if="!role.permissions.length" class="text-gov-muted">No administrative permissions.</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
