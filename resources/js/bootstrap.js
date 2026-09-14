import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

export const currentCsrfToken = () =>
    document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export const syncCsrfToken = (token = currentCsrfToken()) => {
    if (! token) {
        return token;
    }

    let meta = document.head.querySelector('meta[name="csrf-token"]');
    if (! meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        document.head.appendChild(meta);
    }

    meta.setAttribute('content', token);
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

    return token;
};

syncCsrfToken();

window.axios.interceptors.request.use((config) => {
    const token = currentCsrfToken();

    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }

    return config;
});
