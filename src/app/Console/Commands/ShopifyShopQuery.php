<?php

namespace App\Console\Commands;

use App\Exceptions\ShopifyGraphQlException;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Console\Command;

class ShopifyShopQuery extends Command
{
    protected $signature = 'shopify:shop {shop? : myshopify domain, e.g. store.myshopify.com}';

    protected $description = 'Query Shopify shop details via ShopifyAdminApi';

    public function handle(ShopifyAdminApi $shopify): int
    {
        try {
            $data = $shopify->forShop($this->argument('shop'))->getShop();
        } catch (ShopifyGraphQlException $e) {
            $this->error("[{$e->errorCode}] {$e->getMessage()}");
            if ($e->userHint()) {
                $this->warn($e->userHint());
            }

            return self::FAILURE;
        }

        $this->info('Shop query OK');
        $this->table(
            ['Field', 'Value'],
            [
                ['id', $data['id'] ?? ''],
                ['name', $data['name'] ?? ''],
                ['email', $data['email'] ?? ''],
                ['myshopifyDomain', $data['myshopifyDomain'] ?? ''],
                ['currencyCode', $data['currencyCode'] ?? ''],
                ['timezoneAbbreviation', $data['timezoneAbbreviation'] ?? ''],
                ['plan', $data['plan']['displayName'] ?? ''],
                ['partnerDevelopment', isset($data['plan']['partnerDevelopment']) ? json_encode($data['plan']['partnerDevelopment']) : ''],
                ['primaryDomain.host', $data['primaryDomain']['host'] ?? ''],
                ['primaryDomain.url', $data['primaryDomain']['url'] ?? ''],
            ]
        );

        return self::SUCCESS;
    }
}
