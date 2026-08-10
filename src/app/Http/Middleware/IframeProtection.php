<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches the shop domain string only — never the Eloquent model —
 * to avoid Redis unserialize incomplete-object errors after model changes.
 */
class IframeProtection
{
    public function __construct(
        protected IShopQuery $shopQuery
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $ancestors = Util::getShopifyConfig('iframe_ancestors');
        $shopParam = (string) $request->get('shop', '');

        $domain = Cache::remember(
            'frame-ancestors-domain_'.$shopParam,
            now()->addMinutes(20),
            function () use ($request) {
                $shop = $this->shopQuery->getByDomain(ShopDomain::fromRequest($request));

                return $shop?->name;
            }
        );

        $domain = $domain ?: '*.myshopify.com';

        $iframeAncestors = "frame-ancestors https://{$domain} https://admin.shopify.com";

        if (! blank($ancestors)) {
            $iframeAncestors .= ' '.$ancestors;
        }

        $response->headers->set(
            'Content-Security-Policy',
            $iframeAncestors
        );

        return $response;
    }
}
