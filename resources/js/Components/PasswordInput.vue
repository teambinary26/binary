<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    autocomplete: { type: String, default: 'current-password' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const visible = ref(false);
const inputType = computed(() => (visible.value ? 'text' : 'password'));
const label = computed(() => (visible.value ? 'Hide password' : 'Show password'));
</script>

<template>
    <div class="relative">
        <input
            :id="props.id"
            :value="props.modelValue"
            :type="inputType"
            :autocomplete="props.autocomplete"
            :required="props.required"
            class="pr-11"
            @input="emit('update:modelValue', $event.target.value)"
        >
        <button
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center border-0 bg-transparent text-gov-muted hover:bg-transparent hover:text-gov-blue hover:no-underline"
            type="button"
            :aria-label="label"
            :title="label"
            @click="visible = !visible"
        >
            <svg v-if="!visible" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 3l18 18" />
                <path d="M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-4.4" />
                <path d="M9.9 5.2A11 11 0 0112 5c6.5 0 10 7 10 7a18.6 18.6 0 01-3.2 3.8" />
                <path d="M6.1 6.1A18.5 18.5 0 002 12s3.5 7 10 7a10.8 10.8 0 004.3-.9" />
            </svg>
        </button>
    </div>
</template>
