import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';

import AppLayout from './Layouts/AppLayout';
import { bootstrapShopifyEmbeddedApp } from './Shopify';

bootstrapShopifyEmbeddedApp({
    router,
    axios: window.axios,
});

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', {
            eager: true,
        });

        const page = pages[`./Pages/${name}.jsx`];

        if (page?.default && page.default.layout === undefined) {
            page.default.layout = (pageElement) => <AppLayout>{pageElement}</AppLayout>;
        }

        return page;
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
