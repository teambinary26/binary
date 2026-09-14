import { toast } from 'vue-sonner';

const defaults = {
    duration: 4500,
};

let lastFlash = { signature: '', at: 0 };

const shouldSkip = (message) => {
    const signature = String(message || '').trim();
    if (! signature) {
        return true;
    }

    const now = Date.now();
    if (signature === lastFlash.signature && now - lastFlash.at < 1500) {
        return true;
    }

    lastFlash = { signature, at: now };

    return false;
};

export const notify = {
    success(message, options = {}) {
        if (shouldSkip(message)) return;
        return toast.success(message, { ...defaults, ...options });
    },
    error(message, options = {}) {
        if (shouldSkip(message)) return;
        return toast.error(message, { ...defaults, ...options });
    },
    warning(message, options = {}) {
        if (shouldSkip(message)) return;
        return toast.warning(message, { ...defaults, ...options });
    },
    info(message, options = {}) {
        if (shouldSkip(message)) return;
        return toast.info(message, { ...defaults, ...options });
    },
    message(message, options = {}) {
        if (shouldSkip(message)) return;
        return toast(message, { ...defaults, ...options });
    },
    promise(promise, options) {
        return toast.promise(promise, options);
    },
    dismiss(id) {
        return toast.dismiss(id);
    },
};

export function notifyFromFlash(flash) {
    if (! flash) {
        return;
    }

    if (flash.success) {
        notify.success(flash.success);
    }

    if (flash.error) {
        notify.error(flash.error);
    }

    if (flash.warning) {
        notify.warning(flash.warning);
    }

    if (flash.info) {
        notify.info(flash.info);
    }
}

export function useNotify() {
    return { notify, notifyFromFlash };
}
