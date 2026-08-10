<?php

namespace App\Console\Commands;

use App\Exceptions\ShopifyGraphQlException;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Console\Command;

class ShopifyGraphQlPing extends Command
{
    protected $signature = 'shopify:graphql-ping {shop? : myshopify domain, e.g. store.myshopify.com}';

    protected $description = 'Verify GraphQL via the reusable ShopifyAdminApi service';

    public function handle(ShopifyAdminApi $shopify): int
    {
        try {
            $shop = $shopify->forShop($this->argument('shop'))->getShop();
        } catch (ShopifyGraphQlException $e) {
            $this->error("[{$e->errorCode}] {$e->getMessage()}");
            if ($e->userHint()) {
                $this->warn($e->userHint());
            }

            return self::FAILURE;
        }

        $this->info('GraphQL OK');
        $this->line(json_encode([
            'name' => $shop['name'] ?? null,
            'myshopifyDomain' => $shop['myshopifyDomain'] ?? null,
            'primaryDomain' => $shop['primaryDomain'] ?? null,
        ], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
