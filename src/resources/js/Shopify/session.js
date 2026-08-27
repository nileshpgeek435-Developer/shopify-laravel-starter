import { router } from '@inertiajs/react';

import {
    getCachedSessionToken,
    refreshSessionToken,
} from './appBridge';

/**
 * Shopify Admin injects `window.shopify` into embedded apps.
 * Inertia XHR is treated as an API request by verify.shopify and requires
 * Authorization: Bearer <session token>.
 */
export async function getShopifySessionToken() {
    const fresh = await refreshSessionToken();
    if (fresh) {
        return fresh;
    }

    return getCachedSessionToken();
}

export async function shopifyAuthHeaders() {
    const token = await getShopifySessionToken();

    return token ? { Authorization: `Bearer ${token}` } : {};
}

export function syncShopifyAuthHeaders() {
    const token = getCachedSessionToken();
    return token ? { Authorization: `Bearer ${token}` } : {};
}

/**
 * Inertia GET with Shopify session token, or full-page fallback.
 */
export async function visitWithShopifyToken(url, options = {}) {
    const headers = await shopifyAuthHeaders();

    if (!headers.Authorization) {
        window.location.assign(url);
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        ...options,
        headers: {
            ...(options.headers || {}),
            ...headers,
        },
    });
}

/**
 * Inertia POST with Shopify session token, or full-page form fallback.
 */
export async function postWithShopifyToken(url, data = {}, options = {}) {
    const headers = await shopifyAuthHeaders();

    if (!headers.Authorization) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrf) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrf;
            form.appendChild(csrfInput);
        }

        Object.entries(data).forEach(([key, value]) => {
            if (value === undefined || value === null) {
                return;
            }
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(value);
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        return;
    }

    router.post(url, data, {
        preserveScroll: true,
        ...options,
        headers: {
            ...(options.headers || {}),
            ...headers,
        },
    });
}
