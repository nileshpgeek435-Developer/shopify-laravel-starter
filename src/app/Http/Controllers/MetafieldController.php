<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopifyGraphQlException;
use App\Http\Requests\ListMetafieldsRequest;
use App\Http\Requests\StoreMetafieldRequest;
use App\Services\Shopify\ShopifyMetafieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\ShopModel;

class MetafieldController extends Controller
{
    public function index(ListMetafieldsRequest $request, ShopifyMetafieldService $metafields): JsonResponse
    {
        try {
            $shop = $this->shopFromRequest($request);
            $items = $metafields
                ->forShop($shop)
                ->listForOwner(
                    (string) $request->validated('owner_id'),
                    $request->validated('namespace'),
                    (int) $request->integer('first', 20),
                );
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'metafields' => $items,
        ]);
    }

    public function store(StoreMetafieldRequest $request, ShopifyMetafieldService $metafields): JsonResponse
    {
        $data = $request->validated();

        try {
            $shop = $this->shopFromRequest($request);
            $metafield = $metafields
                ->forShop($shop)
                ->setOne(
                    $data['owner_id'],
                    $data['namespace'],
                    $data['key'],
                    $data['value'],
                    $data['type'] ?? 'single_line_text_field',
                );
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'metafield' => $metafield,
        ], 201);
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
        Log::notice('Shopify metafield endpoint failed', $e->toArray() + $e->context);

        return response()->json([
            'error' => $e->toArray(),
        ], $e->httpStatus());
    }
}
