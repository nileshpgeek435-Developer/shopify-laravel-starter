<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Osiset\ShopifyApp\Contracts\ShopModel;
use Osiset\ShopifyApp\Util;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $shopDomain = null;

        if ($user instanceof ShopModel) {
            $shopDomain = $user->getDomain()->toNative();
        } elseif ($request->filled('shop')) {
            $shopDomain = (string) $request->get('shop');
        }

        return [
            ...parent::share($request),
            // Public App Bridge / embedded context only — never api_secret or access tokens.
            'shopify' => [
                'apiKey' => (string) Util::getShopifyConfig('api_key'),
                'shopDomain' => $shopDomain,
                'host' => $request->get('host'),
                'locale' => $request->get('locale'),
                'embedded' => $request->boolean('embedded') || $request->filled('host'),
                'frontendType' => (string) Util::getShopifyConfig('frontend_type'),
            ],
        ];
    }
}
