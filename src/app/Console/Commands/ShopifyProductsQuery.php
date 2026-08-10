<?php

namespace App\Console\Commands;

use App\Exceptions\ShopifyGraphQlException;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Console\Command;

class ShopifyProductsQuery extends Command
{
    protected $signature = 'shopify:products
                            {shop? : myshopify domain, e.g. store.myshopify.com}
                            {--first=10 : Number of products to fetch}';

    protected $description = 'Query Shopify products via ShopifyAdminApi';

    public function handle(ShopifyAdminApi $shopify): int
    {
        $domain = $this->argument('shop');
        $first = max(1, (int) $this->option('first'));

        try {
            $result = $shopify->forShop($domain)->getProducts($first);
        } catch (ShopifyGraphQlException $e) {
            $this->error("[{$e->errorCode}] {$e->getMessage()}");
            if ($e->userHint()) {
                $this->warn($e->userHint());
            }

            return self::FAILURE;
        }

        $rows = collect($result['products'])->map(fn (array $node): array => [
            $node['id'] ?? '',
            $node['title'] ?? '',
            $node['handle'] ?? '',
            $node['status'] ?? '',
            (string) ($node['totalInventory'] ?? ''),
        ])->all();

        $this->info('Products query OK ('.count($rows).' product(s))');
        $this->table(
            ['ID', 'Title', 'Handle', 'Status', 'Inventory'],
            $rows
        );
        $this->line('hasNextPage: '.json_encode($result['hasNextPage']));

        return self::SUCCESS;
    }
}
