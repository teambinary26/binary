<script setup>
import { route } from 'ziggy-js';

const props = defineProps({
    applicationId: { type: [Number, String], required: true },
});

const steps = [
    { label: 'Requirements', name: 'applicant.apply.documents', number: 3 },
    { label: 'Review', name: 'applicant.apply.review', number: 4 },
];

const isCurrent = (name) => route().current(name);
</script>

<template>
    <ol class="mb-4 grid grid-cols-2 border border-gov-border bg-white sm:mb-5">
        <li
            v-for="step in steps"
            :key="step.name"
            class="border-r border-gov-border last:border-r-0"
            :class="isCurrent(step.name) ? 'bg-gov-blue text-white' : 'bg-gov-off'"
        >
            <Link
                :href="route(step.name, props.applicationId)"
                class="block px-2 py-2 text-[10px] font-bold uppercase tracking-wide no-underline sm:px-3 sm:text-xs"
                :class="isCurrent(step.name) ? 'text-white' : 'text-gov-dark'"
            >
                Step {{ step.number }}<br>{{ step.label }}
            </Link>
        </li>
    </ol>
</template>
