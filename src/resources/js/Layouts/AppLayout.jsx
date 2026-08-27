import { usePage } from '@inertiajs/react';

import { onAppLinkClick } from '../Shopify/navigation';
import { withShopifyQuery } from '../Shopify/context';

export default function AppLayout({ children }) {
    const { shopify = {} } = usePage().props;
    const shopDomain = shopify.shopDomain || '';
    const embedded = Boolean(shopify.embedded);

    const dashboardHref = withShopifyQuery('/', shopify);
    const billingHref = withShopifyQuery('/plans', shopify);

    return (
        <div style={{ fontFamily: 'system-ui, sans-serif', minHeight: '100vh' }}>
            <header
                style={{
                    borderBottom: '1px solid #e1e3e5',
                    padding: '12px 20px',
                    display: 'flex',
                    gap: 16,
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    background: '#fff',
                }}
            >
                <strong style={{ marginRight: 8 }}>Shopify Laravel Starter</strong>
                <a
                    href={dashboardHref}
                    onClick={(event) => onAppLinkClick(event, '/', shopify)}
                >
                    Dashboard
                </a>
                <a
                    href={billingHref}
                    onClick={(event) => onAppLinkClick(event, '/plans', shopify)}
                >
                    Billing
                </a>
                <span style={{ marginLeft: 'auto', color: '#6d7175', fontSize: 14 }}>
                    {shopDomain || 'No shop context'}
                    {embedded ? ' · embedded' : ' · standalone'}
                </span>
            </header>
            <div>{children}</div>
        </div>
    );
}
