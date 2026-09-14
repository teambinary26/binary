import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import os from 'node:os';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const __dirname = dirname(fileURLToPath(import.meta.url));

function lanIPv4() {
    const nets = os.networkInterfaces();

    for (const addrs of Object.values(nets)) {
        for (const net of addrs ?? []) {
            const ipv4 = net.family === 'IPv4' || net.family === 4;
            if (ipv4 && ! net.internal) {
                return net.address;
            }
        }
    }

    return '127.0.0.1';
}

function viteHost(env) {
    const fromEnv = env.VITE_DEV_HOST || env.VITE_HMR_HOST;
    if (fromEnv) {
        return fromEnv;
    }

    try {
        const appUrl = env.APP_URL ? new URL(env.APP_URL) : null;
        if (appUrl && appUrl.hostname && appUrl.hostname !== '127.0.0.1' && appUrl.hostname !== 'localhost') {
            return appUrl.hostname;
        }
    } catch (_) {
        // fall through to auto-detect
    }

    return lanIPv4();
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = viteHost(env);

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': resolve(__dirname, 'resources/js'),
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: false,
            cors: true,
            origin: `http://${host}:5173`,
            hmr: {
                host,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
