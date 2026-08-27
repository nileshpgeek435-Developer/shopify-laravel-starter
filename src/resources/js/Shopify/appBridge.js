/**
 * App Bridge CDN bootstrap helpers.
 *
 * Initialization is done by:
 *   <meta name="shopify-api-key" content="...">
 *   <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
 * in resources/views/app.blade.php (same pattern as kyon147/laravel-shopify).
 *
 * No npm @shopify/app-bridge package is required for current App Bridge.
 */

let cachedSessionToken = null;
let refreshTimer = null;

export function getShopifyGlobal() {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.shopify ?? null;
}

export function isEmbeddedShopifyAdmin() {
    const shopify = getShopifyGlobal();
    if (!shopify) {
        return false;
    }

    try {
        return window.top !== window.self;
    } catch {
        // Cross-origin frame access denied ⇒ almost certainly embedded.
        return true;
    }
}

export async function refreshSessionToken() {
    const shopify = getShopifyGlobal();
    if (!shopify?.idToken) {
        cachedSessionToken = null;
        return null;
    }

    try {
        cachedSessionToken = await shopify.idToken();
        return cachedSessionToken;
    } catch {
        cachedSessionToken = null;
        return null;
    }
}

export function getCachedSessionToken() {
    return cachedSessionToken;
}

/**
 * Keep a short-lived in-memory session token for Inertia/axios Authorization headers.
 * Token is never written to localStorage.
 */
export function startSessionTokenRefresh(intervalMs = 2000) {
    if (typeof window === 'undefined' || refreshTimer) {
        return;
    }

    refreshSessionToken();
    refreshTimer = window.setInterval(() => {
        refreshSessionToken();
    }, intervalMs);
}

export async function waitForAppBridge(timeoutMs = 4000) {
    if (getShopifyGlobal()) {
        return getShopifyGlobal();
    }

    const started = Date.now();
    return new Promise((resolve) => {
        const tick = () => {
            const shopify = getShopifyGlobal();
            if (shopify) {
                resolve(shopify);
                return;
            }
            if (Date.now() - started >= timeoutMs) {
                resolve(null);
                return;
            }
            window.setTimeout(tick, 50);
        };
        tick();
    });
}
