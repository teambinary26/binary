<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const page = usePage();
const gov = computed(() => page.props.gov);

const props = defineProps({
    programs: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = useForm({
    category: props.filters.category || '',
    beneficiary: props.filters.beneficiary || '',
    availability: props.filters.availability || '',
    q: props.filters.q || '',
});

let searchTimer = null;

const applyFilters = () => form.get(route('site.programs.index'), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

watch(
    () => [form.q, form.category, form.beneficiary, form.availability],
    (values, previous) => {
        clearTimeout(searchTimer);
        const searchChanged = values[0] !== previous?.[0];
        if (searchChanged && values[0]) {
            searchTimer = setTimeout(applyFilters, 300);
            return;
        }
        applyFilters();
    },
);
</script>

<template>
    <PublicLayout>
        <Head title="Available Assistance Programs" />
        <div class="page-banner">
            <div class="mx-auto max-w-7xl px-4">
                <p class="section-kicker text-[#cfe3f8]">Program directory</p>
                <h1 class="mt-1">Available Assistance Programs</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#d7e6f7] md:text-base">Browse student and general assistance programs administered by {{ gov.agency }}. Program details, amounts, and requirements are maintained by administrators and may change without source-code modification.</p>
            </div>
        </div>
        <div class="mx-auto max-w-7xl px-4 py-6 md:py-8">
            <form class="panel mb-6" @submit.prevent="applyFilters">
                <div class="panel-h-light">Filter programs</div>
                <div class="grid gap-4 p-4 md:grid-cols-4">
                    <div>
                        <label for="category">Program category</label>
                        <select id="category" v-model="form.category">
                            <option value="">All categories</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.group_label }} — {{ category.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="beneficiary">Beneficiary type</label>
                        <select id="beneficiary" v-model="form.beneficiary">
                            <option value="">All</option>
                            <option value="student">Student</option>
                            <option value="non_student">Non-student</option>
                        </select>
                    </div>
                    <div>
                        <label for="availability">Application status</label>
                        <select id="availability" v-model="form.availability">
                            <option value="">All</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label for="q">Search program</label>
                        <input id="q" v-model="form.q" placeholder="Name or code">
                    </div>
                </div>
            </form>

            <div class="grid gap-4">
                <article v-for="program in programs.data" :key="program.id" class="panel">
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-b border-gov-border bg-white px-4 py-2 text-gov-dark">
                        <h2 class="text-base font-bold sm:text-lg">{{ program.name }}</h2>
                        <span class="badge badge-neutral bg-white text-gov-dark">{{ program.code }}</span>
                    </div>
                    <div class="grid gap-4 p-4 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <p class="text-sm">{{ program.description }}</p>
                            <p class="mt-3 text-xs font-bold uppercase tracking-wide text-gov-muted">Eligibility</p>
                            <p class="text-sm">{{ program.eligibility }}</p>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-xs font-bold uppercase text-gov-muted">Category</dt><dd>{{ program.category?.name }} ({{ program.category?.group_label }})</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-gov-muted">Beneficiary type</dt><dd>{{ program.beneficiary_label }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase text-gov-muted">Assistance amount</dt><dd class="font-bold text-gov-dark">{{ program.amount_display }}</dd></div>
                            <div>
                                <dt class="text-xs font-bold uppercase text-gov-muted">Availability</dt>
                                <dd><StatusBadge :label="program.availability_label" :tone="program.is_currently_open ? 'success' : 'neutral'" /></dd>
                            </div>
                        </dl>
                        <div class="flex flex-col justify-end gap-2">
                            <Link class="btn-secondary w-full text-center" :href="route('site.programs.show', program.slug)">View details</Link>
                            <Link v-if="program.is_currently_open" class="btn-primary w-full text-center" :href="route('site.apply.create', program.slug)">Apply</Link>
                        </div>
                    </div>
                </article>
                <p v-if="!programs.data.length" class="panel panel-body text-sm text-gov-muted">No programs match the selected filters.</p>
                <Pagination :paginator="programs" />
            </div>
        </div>
    </PublicLayout>
</template>
