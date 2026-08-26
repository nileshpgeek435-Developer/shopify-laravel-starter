<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;

/**
 * Handles Shopify APP_UNINSTALLED webhooks (POST /webhook/app-uninstalled).
 *
 * Package parent behavior:
 * - cancel the shop's current plan
 * - clear offline access / refresh tokens and plan_id
 * - soft-delete the shop (and related charges)
 * - fire AppUninstalledEvent
 *
 * Add starter-specific cleanup in handle() after parent::handle(), or listen for
 * AppUninstalledEvent from an App\Listeners\* class.
 */
class AppUninstalledJob extends \Osiset\ShopifyApp\Messaging\Jobs\AppUninstalledJob
{
    public function handle(
        IShopCommand $shopCommand,
        IShopQuery $shopQuery,
        CancelCurrentPlan $cancelCurrentPlanAction
    ): bool {
        $shopDomain = $this->shopDomainNative();

        Log::info('Shopify webhook received: app/uninstalled', [
            'shop_domain' => $shopDomain,
        ]);

        $result = parent::handle($shopCommand, $shopQuery, $cancelCurrentPlanAction);

        Log::info('Shopify app/uninstalled handler finished', [
            'shop_domain' => $shopDomain,
            'handled' => $result,
        ]);

        return $result;
    }

    private function shopDomainNative(): string
    {
        if ($this->domain instanceof ShopDomain) {
            return $this->domain->toNative();
        }

        return (string) $this->domain;
    }
}
