import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const ENDED_KEY = 'cams.session.ended';

export const loginPath = () => {
    try {
        return route('login');
    } catch {
        return '/login';
    }
};

export const markSessionEnded = () => {
    try {
        sessionStorage.setItem(ENDED_KEY, '1');
    } catch {
        // sessionStorage may be unavailable
    }

    try {
        router.flushAll?.();
        router.clearHistory?.();
    } catch {
        // Older Inertia builds may not expose these helpers
    }
};

export const markSessionActive = () => {
    try {
        sessionStorage.removeItem(ENDED_KEY);
    } catch {
        // sessionStorage may be unavailable
    }
};

export const sessionWasEnded = () => {
    try {
        return sessionStorage.getItem(ENDED_KEY) === '1';
    } catch {
        return false;
    }
};

export const sendToLogin = () => {
    markSessionEnded();
    window.location.replace(loginPath());
};

export const signOut = (form) => form.post(route('logout'), {
    replace: true,
    onBefore: () => markSessionEnded(),
    onFinish: () => markSessionEnded(),
});

export const installAuthSessionGuard = (initialUser = null) => {
    if (initialUser) {
        markSessionActive();
    }

    window.addEventListener('pageshow', (event) => {
        if (! event.persisted) {
            return;
        }

        if (sessionWasEnded()) {
            sendToLogin();
            return;
        }

        window.location.reload();
    });

    router.on('navigate', (event) => {
        const user = event.detail.page?.props?.auth?.user;

        if (sessionWasEnded() && user) {
            sendToLogin();
        }
    });
};
