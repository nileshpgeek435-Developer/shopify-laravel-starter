<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Messaging\Events\AppUninstalledEvent;

/**
 * App-level hook after the package cleans/soft-deletes the shop.
 * Auto-discovered under App\Listeners — do not also register in shopify-app.php listen.
 */
class HandleAppUninstalled
{
    public function handle(AppUninstalledEvent $event): void
    {
        $shop = $event->shop;

        Log::info('AppUninstalledEvent: shop cleaned and soft-deleted', [
            'shop_id' => $shop->getId()->toNative(),
            'shop_domain' => $shop->getDomain()->toNative(),
        ]);

        // Add starter-specific cleanup here (local caches, app tables, etc.).
    }
}
