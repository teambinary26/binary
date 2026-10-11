<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    municipalityLabel: { type: String, default: 'Municipality' },
    provinceLabel: { type: String, default: 'Province' },
});

const page = usePage();
const residence = computed(() => ({
    municipality: page.props.gov?.residence?.municipality || 'Nabua',
    province: page.props.gov?.residence?.province || 'Camarines Sur',
}));
</script>

<template>
    <div>
        <label for="lgu-municipality">{{ municipalityLabel }}</label>
        <input
            id="lgu-municipality"
            :value="residence.municipality"
            type="text"
            disabled
            spellcheck="false"
            autocomplete="off"
        >
        <input type="hidden" name="municipality" :value="residence.municipality">
    </div>
    <div>
        <label for="lgu-province">{{ provinceLabel }}</label>
        <input
            id="lgu-province"
            :value="residence.province"
            type="text"
            disabled
            spellcheck="false"
            autocomplete="off"
        >
        <input type="hidden" name="province" :value="residence.province">
    </div>
</template>
