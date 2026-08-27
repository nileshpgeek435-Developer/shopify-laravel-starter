import { usePage } from '@inertiajs/react';

import { navigateApp, postApp } from '../Shopify/navigation';
import { withShopifyQuery } from '../Shopify/context';

function money(price) {
    const value = Number(price ?? 0);
    return value.toLocaleString(undefined, { style: 'currency', currency: 'USD' });
}

export default function Billing({
    billing,
    plans = [],
    shopDomain,
    host,
    locale,
    flash = null,
    error = null,
}) {
    const { shopify: sharedShopify = {} } = usePage().props;
    const shopify = {
        ...sharedShopify,
        shopDomain: shopDomain || sharedShopify.shopDomain,
        host: host || sharedShopify.host,
        locale: locale || sharedShopify.locale,
    };

    const dashboardHref = withShopifyQuery('/', shopify);
    const currentName = billing?.current_plan?.name || 'Free';
    const status = billing?.status || 'none';

    const goDashboard = (event) => {
        event.preventDefault();
        navigateApp('/', shopify);
    };

    const subscribe = (planId) => {
        // Hand-off to package /billing/{plan} via Laravel redirect (full page).
        postApp(`/plans/${planId}/subscribe`, {}, shopify);
    };

    const cancel = () => {
        if (!window.confirm('Cancel the current paid plan and return to Free?')) {
            return;
        }

        postApp('/plans/cancel', {}, shopify);
    };

    return (
        <main style={{ maxWidth: 900, margin: '40px auto', padding: '0 20px' }}>
            <p style={{ margin: 0 }}>
                <a href={dashboardHref} onClick={goDashboard}>
                    ← Dashboard
                </a>
            </p>

            <h1 style={{ marginBottom: 8 }}>Billing</h1>
            <p style={{ color: '#555', marginTop: 0 }}>{shopify.shopDomain}</p>

            {flash && (
                <div
                    style={{
                        marginTop: 16,
                        color: flash.type === 'error' ? '#b00020' : '#0b6e4f',
                    }}
                >
                    {flash.message}
                </div>
            )}

            {error && (
                <div style={{ color: '#b00020', marginTop: 16 }}>
                    <strong>Error ({error.code || 'unknown'}):</strong> {error.message}
                </div>
            )}

            <section style={{ marginTop: 28 }}>
                <h2>Current plan</h2>
                <p style={{ marginBottom: 4 }}>
                    <strong>{currentName}</strong>
                    {' — '}
                    <span style={{ textTransform: 'uppercase', letterSpacing: 0.4 }}>{status}</span>
                </p>
                <p style={{ color: '#555', marginTop: 0 }}>
                    {billing?.is_paid
                        ? money(billing?.current_plan?.price)
                        : 'No Shopify charge (freemium / free tier)'}
                </p>

                {billing?.can_cancel && (
                    <button type="button" onClick={cancel} style={{ marginTop: 8 }}>
                        Cancel subscription
                    </button>
                )}
            </section>

            <section style={{ marginTop: 36 }}>
                <h2>Available plans</h2>

                <div style={{ display: 'grid', gap: 16, marginTop: 12 }}>
                    {plans.map((plan) => (
                        <article
                            key={plan.key || plan.id || plan.name}
                            style={{
                                border: '1px solid #ddd',
                                borderRadius: 8,
                                padding: 16,
                                background: plan.is_current ? '#f6fffb' : '#fff',
                            }}
                        >
                            <h3 style={{ margin: '0 0 6px' }}>{plan.name}</h3>
                            <p style={{ margin: '0 0 8px', fontSize: 18 }}>
                                <strong>{money(plan.price)}</strong>
                                {plan.price > 0 ? '/month' : ''}
                            </p>
                            {plan.description && (
                                <p style={{ color: '#555', marginTop: 0 }}>{plan.description}</p>
                            )}
                            {Array.isArray(plan.features) && plan.features.length > 0 && (
                                <ul style={{ margin: '0 0 12px', paddingLeft: 18 }}>
                                    {plan.features.map((feature) => (
                                        <li key={feature}>{feature}</li>
                                    ))}
                                </ul>
                            )}

                            {plan.is_current ? (
                                <strong>Current plan</strong>
                            ) : plan.subscribe_allowed ? (
                                <button type="button" onClick={() => subscribe(plan.id)}>
                                    Choose {plan.name}
                                </button>
                            ) : (
                                <span style={{ color: '#555' }}>Included as freemium</span>
                            )}
                        </article>
                    ))}
                </div>
            </section>
        </main>
    );
}
