<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    municipalityLabel: { type: String, default: 'Municipality' },
    provinceLabel: { type: String, default: 'Province' },
    municipality: { type: String, default: '' },
    province: { type: String, default: '' },
});

const page = usePage();
const residence = computed(() => ({
    municipality: props.municipality || page.props.gov?.residence?.municipality || 'Nabua',
    province: props.province || page.props.gov?.residence?.province || 'Camarines Sur',
}));
</script>

<template>
    <div class="contents">
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
        </div>
    </div>
</template>
