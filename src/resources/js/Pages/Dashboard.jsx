export default function Dashboard({ shop, products = [], hasNextPage = false, error = null }) {
    const planName = shop?.plan?.displayName || '—';
    const isDevPlan = Boolean(shop?.plan?.partnerDevelopment);

    return (
        <main style={{ fontFamily: 'system-ui, sans-serif', maxWidth: 900, margin: '40px auto', padding: '0 20px' }}>
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
        </main>
    );
}
