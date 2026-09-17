<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidenavLogo from '@/Components/SidenavLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const portalLabel = computed(() => {
    const slug = user.value?.role_slug;

    if (slug === 'administrator' || user.value?.is_admin) {
        return 'Admin Portal';
    }

    if (slug === 'sk' || user.value?.is_sk) {
        return 'SK Portal';
    }

    if (slug === 'staff' || user.value?.is_staff) {
        return 'Staff Portal';
    }

    if (slug === 'applicant' || user.value?.is_applicant) {
        return 'Applicant Portal';
    }

    return user.value?.role_name ? `${user.value.role_name} Portal` : 'Portal';
});
</script>

<template>
    <div class="flex flex-col items-center px-4 py-4 text-center">
        <SidenavLogo class="h-[4.5rem] w-[4.5rem]" />
        <p class="mt-3 text-[10px] font-bold uppercase tracking-[0.16em] text-white">{{ portalLabel }}</p>
    </div>
</template>
