<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopifyGraphQlException;
use App\Http\Requests\ListMetaobjectsRequest;
use App\Http\Requests\StoreMetaobjectRequest;
use App\Http\Requests\UpdateMetaobjectRequest;
use App\Services\Shopify\ShopifyMetaobjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\ShopModel;

class MetaobjectController extends Controller
{
    public function index(ListMetaobjectsRequest $request, ShopifyMetaobjectService $metaobjects): JsonResponse
    {
        try {
            $shop = $this->shopFromRequest($request);
            $data = $metaobjects
                ->forShop($shop)
                ->list(
                    (string) $request->validated('type'),
                    (int) $request->integer('first', 10),
                );
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json($data);
    }

    public function show(Request $request, string $id, ShopifyMetaobjectService $metaobjects): JsonResponse
    {
        try {
            $shop = $this->shopFromRequest($request);
            $metaobject = $metaobjects->forShop($shop)->find($id);
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'metaobject' => $metaobject,
        ]);
    }

    public function store(StoreMetaobjectRequest $request, ShopifyMetaobjectService $metaobjects): JsonResponse
    {
        $input = $request->validated();

        try {
            $shop = $this->shopFromRequest($request);
            $metaobject = $metaobjects->forShop($shop)->create($input);
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'metaobject' => $metaobject,
        ], 201);
    }

    public function update(
        UpdateMetaobjectRequest $request,
        string $id,
        ShopifyMetaobjectService $metaobjects,
    ): JsonResponse {
        $input = array_filter(
            $request->validated(),
            fn ($value) => $value !== null,
        );

        try {
            $shop = $this->shopFromRequest($request);
            $metaobject = $metaobjects->forShop($shop)->update($id, $input);
        } catch (ShopifyGraphQlException $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'metaobject' => $metaobject,
        ]);
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
        Log::notice('Shopify metaobject endpoint failed', $e->toArray() + $e->context);

        return response()->json([
            'error' => $e->toArray(),
        ], $e->httpStatus());
    }
}
