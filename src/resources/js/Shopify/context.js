/**
 * Shared Shopify request context helpers (shop / host / locale).
 */

export function readShopifyQueryFromUrl() {
    if (typeof window === 'undefined') {
        return { shop: '', host: '', locale: '' };
    }

    const params = new URLSearchParams(window.location.search);

    return {
        shop: params.get('shop') || '',
        host: params.get('host') || '',
        locale: params.get('locale') || '',
    };
}

export function buildShopifyQuery(shopify = {}, overrides = {}) {
    const fromUrl = readShopifyQueryFromUrl();
    const params = new URLSearchParams();

    const shop = overrides.shop ?? shopify.shopDomain ?? fromUrl.shop ?? '';
    const host = overrides.host ?? shopify.host ?? fromUrl.host ?? '';
    const locale = overrides.locale ?? shopify.locale ?? fromUrl.locale ?? '';

    if (shop) {
        params.set('shop', shop);
    }
    if (host) {
        params.set('host', host);
    }
    if (locale) {
        params.set('locale', locale);
    }

    return params;
}

export function withShopifyQuery(path, shopify = {}, overrides = {}) {
    const params = buildShopifyQuery(shopify, overrides);
    const qs = params.toString();
    const [base, existing] = String(path).split('?', 2);

    if (!qs) {
        return existing ? `${base}?${existing}` : base;
    }

    if (!existing) {
        return `${base}?${qs}`;
    }

    const merged = new URLSearchParams(existing);
    params.forEach((value, key) => merged.set(key, value));

    return `${base}?${merged.toString()}`;
}

export function shopifyQueryObject(shopify = {}) {
    const params = buildShopifyQuery(shopify);
    return Object.fromEntries(params.entries());
}
