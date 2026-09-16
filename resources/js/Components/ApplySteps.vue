<script setup>
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    application: { type: Object, required: true },
});

const hasFormFields = computed(() => (props.application.program_detail?.form_fields || []).length > 0);

const steps = computed(() => {
    const items = [];
    if (hasFormFields.value) {
        items.push({ label: 'Form', name: 'applicant.apply.form' });
    }
    items.push({ label: 'Requirements', name: 'applicant.apply.documents' });
    items.push({ label: 'Review', name: 'applicant.apply.review' });

    return items.map((step, index) => ({
        ...step,
        number: index + 1,
    }));
});

const isCurrent = (name) => route().current(name);
</script>

<template>
    <ol class="mb-4 grid border border-gov-border bg-white sm:mb-5" :class="steps.length === 3 ? 'grid-cols-3' : 'grid-cols-2'">
        <li
            v-for="step in steps"
            :key="step.name"
            class="border-r border-gov-border last:border-r-0"
            :class="isCurrent(step.name) ? 'bg-gov-blue text-white' : 'bg-gov-off'"
        >
            <Link
                :href="route(step.name, props.application.id)"
                class="block px-2 py-2 text-[10px] font-bold uppercase tracking-wide no-underline sm:px-3 sm:text-xs"
                :class="isCurrent(step.name) ? 'text-white' : 'text-gov-dark'"
            >
                Step {{ step.number }}<br>{{ step.label }}
            </Link>
        </li>
    </ol>
</template>
