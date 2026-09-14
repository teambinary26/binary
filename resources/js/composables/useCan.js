import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useCan() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);

    const can = (permission) => {
        if (!user.value) {
            return false;
        }

        if (user.value.is_admin || user.value.is_super_admin) {
            return true;
        }

        return (user.value.permissions ?? []).includes(permission);
    };

    return { can, user };
}
