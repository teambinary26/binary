<script setup>
const props = defineProps({
    fields: { type: Array, default: () => [] },
    model: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    errorPrefix: { type: String, default: '' },
    disabledNames: { type: Array, default: () => [] },
});

const errorFor = (name) => props.errors[`${props.errorPrefix}${name}`];
const isDisabled = (name) => props.disabledNames.includes(name);
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <div
            v-for="field in fields"
            :key="field.name"
            :class="{ 'md:col-span-2': field.type === 'textarea' }"
        >
            <label :for="`field_${field.name}`">
                {{ field.label }}
                <template v-if="field.is_required && ! isDisabled(field.name)"> *</template>
            </label>
            <textarea
                v-if="field.type === 'textarea'"
                :id="`field_${field.name}`"
                v-model="model[field.name]"
                rows="4"
                :required="field.is_required && ! isDisabled(field.name)"
                :disabled="isDisabled(field.name)"
            />
            <select
                v-else-if="field.type === 'select'"
                :id="`field_${field.name}`"
                v-model="model[field.name]"
                :required="field.is_required && ! isDisabled(field.name)"
                :disabled="isDisabled(field.name)"
            >
                <option value="">Select</option>
                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
            </select>
            <input
                v-else
                :id="`field_${field.name}`"
                v-model="model[field.name]"
                :type="field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text')"
                :step="field.type === 'number' ? 'any' : undefined"
                :required="field.is_required && ! isDisabled(field.name)"
                :disabled="isDisabled(field.name)"
            >
            <p v-if="field.help_text" class="mt-1 text-xs text-gov-muted">{{ field.help_text }}</p>
            <p v-if="errorFor(field.name)" class="field-error">{{ errorFor(field.name) }}</p>
        </div>
    </div>
</template>
