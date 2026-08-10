<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopifyGraphQlException;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\ShopModel;

class ShopifyController extends Controller
{
    public function shop(Request $request, ShopifyAdminApi $shopify): JsonResponse
    {
        try {
            $shop = $this->shopFromRequest($request);
            $data = $shopify->forShop($shop)->getShop();
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'shop' => $data,
        ]);
    }

    public function products(Request $request, ShopifyAdminApi $shopify): JsonResponse
    {
        $first = max(1, min((int) $request->integer('first', 10), 50));

        try {
            $shop = $this->shopFromRequest($request);
            $data = $shopify->forShop($shop)->getProducts($first);
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json($data);
    }

    private function shopFromRequest(Request $request): ShopModel
    {
        $user = $request->user();

        if (! $user instanceof ShopModel) {
            abort(401, 'Unauthenticated shop.');
        }

        return $user;
    }

    private function errorResponse(ShopifyGraphQlException $e): JsonResponse
    {
        Log::notice('Shopify API endpoint failed', $e->toArray() + $e->context);

        return response()->json([
            'error' => $e->toArray(),
        ], $e->httpStatus());
    }
}
