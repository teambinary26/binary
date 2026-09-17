import { ref } from 'vue';

export function useSidebarCollapse(storageKey) {
    const collapsed = ref(false);

    if (typeof window !== 'undefined') {
        collapsed.value = window.localStorage.getItem(storageKey) === '1';
    }

    const toggle = () => {
        collapsed.value = ! collapsed.value;

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(storageKey, collapsed.value ? '1' : '0');
        }
    };

    return { collapsed, toggle };
}
