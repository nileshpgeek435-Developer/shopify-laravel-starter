import { isEmbeddedShopifyAdmin } from './appBridge';
import { withShopifyQuery } from './context';
import { postWithShopifyToken, visitWithShopifyToken } from './session';

/**
 * Embedded-aware navigation helpers.
 *
 * - Internal app pages: Inertia + session token (keeps Shopify Admin iframe).
 * - External / leave-iframe URLs: top-level navigation.
 * - OAuth + package billing confirmation remain full-page (handled by Laravel/package).
 */

function isAbsoluteUrl(url) {
    return /^https?:\/\//i.test(url) || url.startsWith('//');
}

function isShopifyAdminUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.hostname === 'admin.shopify.com' || parsed.hostname.endsWith('.myshopify.com');
    } catch {
        return false;
    }
}

function isBillingOrOAuthPath(path) {
    return (
        path.startsWith('/authenticate') ||
        path.startsWith('/billing/') ||
        path === '/billing'
    );
}

/**
 * Navigate inside the embedded app (Dashboard, /plans, etc.).
 */
export function navigateApp(path, shopify = {}, options = {}) {
    const url = withShopifyQuery(path, shopify);

    if (isBillingOrOAuthPath(path.split('?', 1)[0])) {
        // Package billing/OAuth expect a full document load.
        window.location.assign(url);
        return;
    }

    return visitWithShopifyToken(url, options);
}

/**
 * POST inside the embedded app (e.g. subscribe/cancel) with session token.
 */
export function postApp(path, data = {}, shopify = {}, options = {}) {
    const url = withShopifyQuery(path, shopify);
    const payload = {
        ...shopifyQueryPayload(shopify),
        ...data,
    };

    return postWithShopifyToken(url, payload, options);
}

function shopifyQueryPayload(shopify = {}) {
    const payload = {};
    if (shopify.shopDomain) {
        payload.shop = shopify.shopDomain;
    }
    if (shopify.host) {
        payload.host = shopify.host;
    }
    if (shopify.locale) {
        payload.locale = shopify.locale;
    }
    return payload;
}

/**
 * Leave the app iframe when needed (docs, Partners, external sites).
 */
export function navigateExternal(url) {
    const target = String(url);
    if (isEmbeddedShopifyAdmin() || isShopifyAdminUrl(target) || isAbsoluteUrl(target)) {
        window.open(target, '_top');
        return;
    }

    window.location.assign(target);
}

/**
 * Click handler for internal app links.
 */
export function onAppLinkClick(event, path, shopify = {}) {
    if (event.defaultPrevented) {
        return;
    }
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    navigateApp(path, shopify);
}
