<?php

/**
 * Application-level Shopify billing plans for this starter.
 *
 * Paid plans are synced into the package `plans` table by ShopifyPlanSeeder.
 * The Free tier uses package freemium (no Shopify charge) when
 * SHOPIFY_BILLING_FREEMIUM_ENABLED=true.
 *
 * Prices are starter placeholders — change them here before seeding/deploying.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Free (freemium) tier
    |--------------------------------------------------------------------------
    |
    | Not stored as a Shopify application charge. Represented by
    | users.shopify_freemium / absence of plan_id.
    |
    */

    'free' => [
        'key' => 'free',
        'name' => 'Free',
        'price' => 0.00,
        'interval' => null,
        'trial_days' => 0,
        'description' => 'Starter freemium access with core features.',
        'features' => [
            'Dashboard access',
            'Shop + products GraphQL demo',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paid plans (synced to `plans` table)
    |--------------------------------------------------------------------------
    |
    | `type`: recurring|onetime (maps to package PlanType)
    | `interval`: EVERY_30_DAYS|ANNUAL (package PlanInterval)
    | `on_install`: package default plan when billing redirects without plan id
    | `test`: forced true when SHOPIFY_BILLING_TEST_CHARGES=true
    |
    */

    'paid' => [
        'basic' => [
            'key' => 'basic',
            'name' => 'Basic',
            'price' => 9.99,
            'type' => 'recurring',
            'interval' => 'EVERY_30_DAYS',
            'trial_days' => 7,
            'capped_amount' => null,
            'terms' => null,
            'on_install' => true,
            'test' => true,
            'description' => 'For small shops getting started.',
            'features' => [
                'Everything in Free',
                'Priority support (template)',
            ],
        ],
        'pro' => [
            'key' => 'pro',
            'name' => 'Pro',
            'price' => 29.99,
            'type' => 'recurring',
            'interval' => 'EVERY_30_DAYS',
            'trial_days' => 7,
            'capped_amount' => null,
            'terms' => null,
            'on_install' => false,
            'test' => true,
            'description' => 'For growing shops that need more capacity.',
            'features' => [
                'Everything in Basic',
                'Advanced features (template)',
            ],
        ],
    ],
];
