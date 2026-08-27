import { startSessionTokenRefresh, waitForAppBridge } from './appBridge';
import { syncShopifyAuthHeaders } from './session';

/**
 * Central App Bridge + Inertia session-token wiring.
 * Call once from resources/js/app.jsx.
 */
export async function bootstrapShopifyEmbeddedApp({ router, axios } = {}) {
    await waitForAppBridge();
    startSessionTokenRefresh(2000);

    if (axios?.defaults?.headers) {
        const applyAxiosToken = () => {
            const headers = syncShopifyAuthHeaders();
            if (headers.Authorization) {
                axios.defaults.headers.common.Authorization = headers.Authorization;
            }
        };
        applyAxiosToken();
        window.setInterval(applyAxiosToken, 2000);
    }

    if (router?.on) {
        router.on('before', (event) => {
            const headers = syncShopifyAuthHeaders();
            if (!headers.Authorization) {
                return;
            }

            event.detail.visit.headers = {
                ...(event.detail.visit.headers || {}),
                ...headers,
            };
        });
    }
}
