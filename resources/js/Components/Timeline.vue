<script setup>
defineProps({
    steps: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <ol class="space-y-0 border border-gov-border bg-white">
        <li
            v-for="step in steps"
            :key="step.index"
            class="flex min-w-0 border-b border-gov-border last:border-b-0"
            :class="compact ? 'gap-2' : 'gap-3 sm:gap-4'"
        >
            <div
                class="flex shrink-0 items-center justify-center border-r border-gov-border"
                :class="[
                    compact ? 'w-8' : 'w-12 sm:w-16',
                    {
                        'bg-gov-success text-white': step.state === 'done',
                        'bg-gov-blue text-white': step.state === 'current',
                        'bg-gov-danger text-white': step.state === 'rejected',
                        'bg-gov-off text-gov-muted': step.state === 'upcoming',
                    },
                ]"
            >
                <span class="font-bold" :class="compact ? 'text-xs' : 'text-sm'">{{ step.index }}</span>
            </div>
            <div class="min-w-0" :class="compact ? 'py-2 pr-2' : 'py-3 pr-3 sm:pr-4'">
                <p class="font-bold text-gov-dark" :class="compact ? 'text-sm leading-tight' : ''">{{ step.label }}</p>
                <p class="break-words text-xs text-gov-muted" :class="compact ? 'leading-snug' : ''">{{ step.description }}</p>
            </div>
        </li>
    </ol>
</template>
