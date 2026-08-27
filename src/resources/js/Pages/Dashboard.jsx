import { usePage } from '@inertiajs/react';

import { onAppLinkClick } from '../Shopify/navigation';
import { withShopifyQuery } from '../Shopify/context';

export default function Dashboard({
    shop,
    products = [],
    hasNextPage = false,
    shopMetafields = [],
    error = null,
}) {
    const { shopify = {} } = usePage().props;
    const planName = shop?.plan?.displayName || '—';
    const isDevPlan = Boolean(shop?.plan?.partnerDevelopment);
    const billingHref = withShopifyQuery('/plans', shopify, {
        shop: shopify.shopDomain || shop?.myshopifyDomain,
    });

    return (
        <main style={{ maxWidth: 900, margin: '40px auto', padding: '0 20px' }}>
            <h1 style={{ marginBottom: 8 }}>{shop?.name?.trim() || 'Dashboard'}</h1>
            <p style={{ color: '#555', marginTop: 0 }}>{shop?.myshopifyDomain || ''}</p>

            {error && (
                <div style={{ color: '#b00020', marginTop: 16 }}>
                    <p style={{ margin: 0 }}>
                        <strong>Error ({error.code || 'unknown'}):</strong> {error.message}
                    </p>
                    {error.hint && <p style={{ margin: '6px 0 0' }}>{error.hint}</p>}
                </div>
            )}

            {shop && (
                <section style={{ marginTop: 28 }}>
                    <h2>Shop</h2>
                    <p>Email: {shop.email || '—'}</p>
                    <p>Currency: {shop.currencyCode || '—'}</p>
                    <p>
                        Plan: <strong>{planName}</strong>
                        {isDevPlan ? ' (development)' : ''}
                    </p>
                    <p>Domain: {shop.primaryDomain?.host || '—'}</p>
                    <p>
                        <a
                            href={billingHref}
                            onClick={(event) => onAppLinkClick(event, '/plans', shopify)}
                        >
                            Manage app billing →
                        </a>
                    </p>
                </section>
            )}

            <section style={{ marginTop: 28 }}>
                <h2>Products {hasNextPage ? '(more available)' : ''}</h2>

                {products.length === 0 ? (
                    <p>{error ? 'Products unavailable.' : 'No products to show.'}</p>
                ) : (
                    <ul style={{ paddingLeft: 18 }}>
                        {products.map((product) => (
                            <li key={product.id} style={{ marginBottom: 8 }}>
                                <strong>{product.title}</strong>
                                {' — '}
                                {product.status}
                                {' — inventory: '}
                                {product.totalInventory ?? '—'}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section style={{ marginTop: 28 }}>
                <h2>Shop metafields (demo)</h2>
                <p style={{ color: '#555', fontSize: 14 }}>
                    Shows namespace <code>starter</code> only (via ShopifyMetafieldService). Use
                    /api/metafields and /api/metaobjects for full CRUD. Sensitive keys are redacted.
                </p>
                {shopMetafields.length === 0 ? (
                    <p>No starter.* shop metafields yet.</p>
                ) : (
                    <ul style={{ paddingLeft: 18 }}>
                        {shopMetafields.map((field) => {
                            const label = `${field.namespace}.${field.key}`;
                            const sensitive = /secret|password|token|private|credential/i.test(
                                field.key || '',
                            );
                            const raw = field.value ?? '—';
                            const display = sensitive
                                ? '[redacted]'
                                : raw.length > 120
                                  ? `${raw.slice(0, 120)}…`
                                  : raw;

                            return (
                                <li key={field.id || label} style={{ marginBottom: 8 }}>
                                    <strong>{label}</strong>
                                    {' — '}
                                    {field.type}
                                    {' — '}
                                    {display}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>
        </main>
    );
}
