<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopifyGraphQlException;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Osiset\ShopifyApp\Contracts\ShopModel;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ShopifyAdminApi $shopify): Response
    {
        $user = $request->user();

        if (! $user instanceof ShopModel) {
            abort(401, 'Unauthenticated shop.');
        }

        $api = $shopify->forShop($user);

        $shop = null;
        $products = [];
        $hasNextPage = false;
        $error = null;

        try {
            $shop = $api->getShop();
        } catch (ShopifyGraphQlException $e) {
            Log::notice('Dashboard shop load failed', $e->toArray() + $e->context);
            $error = $e->toArray();
        }

        // Still try products when shop load succeeded; keep partial UI working.
        if ($error === null) {
            try {
                $productPage = $api->getProducts(10);
                $products = $productPage['products'];
                $hasNextPage = $productPage['hasNextPage'];
            } catch (ShopifyGraphQlException $e) {
                Log::notice('Dashboard products load failed', $e->toArray() + $e->context);
                $error = $e->toArray();
            }
        }

        return Inertia::render('Dashboard', [
            'shop' => $shop,
            'products' => $products,
            'hasNextPage' => $hasNextPage,
            'error' => $error,
        ]);
    }
}
