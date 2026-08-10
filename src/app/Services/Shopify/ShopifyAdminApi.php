<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\ShopModel;
use Throwable;

class ShopifyAdminApi
{
    private const SHOP_QUERY = <<<'GRAPHQL'
    {
      shop {
        id
        name
        email
        myshopifyDomain
        currencyCode
        timezoneAbbreviation
        plan {
          displayName
          partnerDevelopment
        }
        primaryDomain {
          host
          url
        }
      }
    }
    GRAPHQL;

    private const PRODUCTS_QUERY = <<<'GRAPHQL'
    query Products($first: Int!) {
      products(first: $first) {
        edges {
          node {
            id
            title
            handle
            status
            totalInventory
            featuredImage {
              url
            }
          }
        }
        pageInfo {
          hasNextPage
          endCursor
        }
      }
    }
    GRAPHQL;

    private ?ShopModel $shop = null;

    public function forShop(ShopModel|User|string|null $shop = null): self
    {
        $resolved = $this->resolveShop($shop);

        $instance = clone $this;
        $instance->shop = $resolved;

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graph(string $query, array $variables = []): array
    {
        $shop = $this->requireShop();
        $domain = $shop->getDomain()->toNative();

        if (! $shop->hasOfflineAccess()) {
            throw new ShopifyGraphQlException(
                'Shop has no offline access token. Reinstall / authenticate the app.',
                errorCode: ShopifyGraphQlException::CODE_MISSING_TOKEN,
                context: ['shop' => $domain],
            );
        }

        try {
            $response = $shop->api()->graph($query, $variables);
        } catch (Throwable $e) {
            Log::error('Shopify GraphQL client exception', [
                'shop' => $domain,
                'message' => $e->getMessage(),
            ]);

            throw new ShopifyGraphQlException(
                'Shopify GraphQL request failed.',
                errorCode: ShopifyGraphQlException::CODE_TRANSPORT,
                context: ['shop' => $domain, 'exception' => $e->getMessage()],
                previous: $e,
            );
        }

        $status = isset($response['status']) ? (int) $response['status'] : null;

        if (($response['errors'] ?? false) === true) {
            $body = $response['body'] ?? null;
            $message = is_string($body) ? $body : 'Shopify GraphQL transport/API error.';
            $errorCode = $this->classifyTransportError($status, $message);

            Log::warning('Shopify GraphQL transport error', [
                'shop' => $domain,
                'status' => $status,
                'code' => $errorCode,
                'message' => $message,
            ]);

            throw new ShopifyGraphQlException(
                $message,
                response: is_array($body) ? $body : ['body' => $body],
                status: $status,
                errorCode: $errorCode,
                context: ['shop' => $domain],
            );
        }

        $decoded = $this->normalizeBody($response['body'] ?? null);

        if (! empty($decoded['errors']) && is_array($decoded['errors'])) {
            $messages = collect($decoded['errors'])
                ->map(fn (array $error): string => (string) ($error['message'] ?? json_encode($error)))
                ->implode('; ');

            $errorCode = $this->classifyGraphQlErrors($decoded['errors']);

            Log::warning('Shopify GraphQL response errors', [
                'shop' => $domain,
                'status' => $status,
                'code' => $errorCode,
                'errors' => $decoded['errors'],
            ]);

            throw new ShopifyGraphQlException(
                $messages !== '' ? $messages : 'Shopify GraphQL returned errors.',
                errors: $decoded['errors'],
                response: $decoded,
                status: $status,
                errorCode: $errorCode,
                context: ['shop' => $domain],
            );
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function getShop(): array
    {
        $decoded = $this->graph(self::SHOP_QUERY);
        $shop = $decoded['data']['shop'] ?? null;

        if (! is_array($shop)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.shop).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['shop' => $this->requireShop()->getDomain()->toNative()],
            );
        }

        return $shop;
    }

    /**
     * @return array{products: list<array<string, mixed>>, hasNextPage: bool, endCursor: ?string}
     */
    public function getProducts(int $first = 10): array
    {
        $first = max(1, min($first, 50));
        $decoded = $this->graph(self::PRODUCTS_QUERY, ['first' => $first]);
        $products = $decoded['data']['products'] ?? null;

        if (! is_array($products)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.products).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['shop' => $this->requireShop()->getDomain()->toNative()],
            );
        }

        $nodes = collect($products['edges'] ?? [])
            ->map(fn (array $edge): array => $edge['node'] ?? [])
            ->filter()
            ->values()
            ->all();

        return [
            'products' => $nodes,
            'hasNextPage' => (bool) ($products['pageInfo']['hasNextPage'] ?? false),
            'endCursor' => $products['pageInfo']['endCursor'] ?? null,
        ];
    }

    public function resolveShop(ShopModel|User|string|null $shop = null): ShopModel
    {
        if ($shop instanceof ShopModel) {
            return $shop;
        }

        $domain = is_string($shop) && $shop !== ''
            ? $shop
            : User::query()->value('name');

        if (! $domain) {
            throw new ShopifyGraphQlException(
                'No shop found. Install the app from Shopify Admin first.',
                errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
            );
        }

        $model = User::query()->where('name', $domain)->first();

        if (! $model) {
            throw new ShopifyGraphQlException(
                "Shop not found: {$domain}",
                errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
                context: ['shop' => $domain],
            );
        }

        return $model;
    }

    private function requireShop(): ShopModel
    {
        if ($this->shop === null) {
            $this->shop = $this->resolveShop();
        }

        return $this->shop;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBody(mixed $body): array
    {
        if (is_array($body)) {
            return $body;
        }

        $decoded = json_decode(json_encode($body), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function classifyTransportError(?int $status, string $message): string
    {
        $lower = strtolower($message);

        if ($status === 401 || str_contains($lower, 'invalid api key') || str_contains($lower, 'unrecognized login')) {
            return ShopifyGraphQlException::CODE_UNAUTHORIZED;
        }

        if ($status === 403 || str_contains($lower, 'access denied') || str_contains($lower, 'requires merchant approval')) {
            return ShopifyGraphQlException::CODE_ACCESS_DENIED;
        }

        if ($status === 429 || str_contains($lower, 'rate limit') || str_contains($lower, 'exceeded')) {
            return ShopifyGraphQlException::CODE_RATE_LIMITED;
        }

        if (str_contains($lower, 'non-expiring access tokens are no longer accepted')) {
            return ShopifyGraphQlException::CODE_UNAUTHORIZED;
        }

        return ShopifyGraphQlException::CODE_TRANSPORT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function classifyGraphQlErrors(array $errors): string
    {
        foreach ($errors as $error) {
            $code = $error['extensions']['code'] ?? null;

            if ($code === 'ACCESS_DENIED') {
                return ShopifyGraphQlException::CODE_ACCESS_DENIED;
            }

            if ($code === 'THROTTLED') {
                return ShopifyGraphQlException::CODE_RATE_LIMITED;
            }
        }

        return ShopifyGraphQlException::CODE_UNEXPECTED;
    }
}
