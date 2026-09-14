<script setup>
import { computed } from 'vue';
import { route } from 'ziggy-js';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    cards: { type: Array, default: () => [] },
    canManageSettings: { type: Boolean, default: false },
});

const processCards = computed(() => props.cards.filter((card) => card.order));
const outcomeCards = computed(() => props.cards.filter((card) => ! card.order));

const badgeClass = (tone) => ({
    info: 'bg-gov-blue',
    warning: 'bg-amber-500',
    success: 'bg-green-600',
    danger: 'bg-red-600',
}[tone] || 'bg-gov-blue');

const href = (card) => route(card.route, card.route_params || {});
</script>

<template>
    <AdminLayout>
        <Head title="Application Workflow" />
        <PageHeader title="Application Workflow" kicker="Process applications part by part">
            <template v-if="canManageSettings" #actions>
                <Link :href="route('admin.settings.edit')" class="btn-secondary btn-sm">Assign workflow staff</Link>
            </template>
        </PageHeader>

        <p class="mb-4 text-sm text-gov-muted">
            Each card is one part of the application process. Open a card to work on the applications in that step.
        </p>

        <div class="mb-4 grid gap-4 lg:grid-cols-3">
            <article v-for="card in processCards" :key="card.key" class="panel flex flex-col">
                <div class="panel-h flex items-center justify-between gap-3">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white" :class="badgeClass(card.tone)">{{ card.order }}</span>
                        <span class="truncate">{{ card.title }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-gov-muted">{{ card.count }}</span>
                </div>
                <div class="panel-body flex flex-1 flex-col gap-3">
                    <p class="text-sm text-gov-muted">{{ card.description }}</p>
                    <p class="text-xs">
                        <span class="font-bold uppercase tracking-wide text-gov-muted">Assigned staff</span><br>
                        <span v-if="card.assigned_staff" class="break-words font-semibold text-gov-blue">{{ card.assigned_staff }}</span>
                        <span v-else class="italic text-gray-400">Unassigned</span>
                    </p>
                    <ul class="divide-y divide-gov-border border border-gov-border text-sm">
                        <li v-if="! card.can_open" class="px-3 py-3 text-xs italic text-gov-muted">You are not assigned to this step.</li>
                        <li v-else-if="! card.applications.length" class="px-3 py-3 text-xs italic text-gov-muted">No applications in this step.</li>
                        <li v-for="row in card.applications" :key="row.id" class="flex items-start justify-between gap-2 px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gov-blue">
                                    <Link :href="route('admin.applications.show', row.id)">{{ row.application_no }}</Link>
                                </p>
                                <p class="truncate text-xs text-gov-muted">{{ row.applicant?.full_name }}</p>
                            </div>
                            <StatusBadge :label="row.status_label" :tone="row.status_tone" />
                        </li>
                    </ul>
                    <Link
                        v-if="card.can_open"
                        :href="href(card)"
                        class="btn-primary btn-sm mt-auto w-full text-center"
                    >{{ card.action_label }}</Link>
                    <p v-else class="mt-auto text-center text-xs italic text-gov-muted">Ask an administrator to assign you to this step.</p>
                </div>
            </article>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="card in outcomeCards" :key="card.key" class="panel flex flex-col">
                <div class="panel-h flex items-center justify-between gap-3">
                    <span>{{ card.title }}</span>
                    <span class="text-xs font-bold text-gov-muted">{{ card.count }}</span>
                </div>
                <div class="panel-body flex flex-1 flex-col gap-3">
                    <p class="text-sm text-gov-muted">{{ card.description }}</p>
                    <ul class="divide-y divide-gov-border border border-gov-border text-sm">
                        <li v-if="! card.applications.length" class="px-3 py-3 text-xs italic text-gov-muted">No applications in this step.</li>
                        <li v-for="row in card.applications" :key="row.id" class="flex items-start justify-between gap-2 px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gov-blue">
                                    <Link :href="route('admin.applications.show', row.id)">{{ row.application_no }}</Link>
                                </p>
                                <p class="truncate text-xs text-gov-muted">{{ row.applicant?.full_name }}</p>
                            </div>
                            <StatusBadge :label="row.status_label" :tone="row.status_tone" />
                        </li>
                    </ul>
                    <Link :href="href(card)" class="btn-secondary btn-sm mt-auto w-full text-center">{{ card.action_label }}</Link>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>
