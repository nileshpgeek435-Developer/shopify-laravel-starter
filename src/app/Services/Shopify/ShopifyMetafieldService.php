<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\Concerns\AssertsShopifyUserErrors;
use Osiset\ShopifyApp\Contracts\ShopModel;

/**
 * Reusable Admin GraphQL helpers for Shopify metafields.
 */
class ShopifyMetafieldService
{
    use AssertsShopifyUserErrors;

    private const OWNER_METAFIELDS_QUERY = <<<'GRAPHQL'
    query OwnerMetafields($ownerId: ID!, $namespace: String, $first: Int!) {
      node(id: $ownerId) {
        id
        ... on HasMetafields {
          metafields(first: $first, namespace: $namespace) {
            edges {
              node {
                id
                namespace
                key
                type
                value
              }
            }
          }
        }
      }
    }
    GRAPHQL;

    private const OWNER_METAFIELD_QUERY = <<<'GRAPHQL'
    query OwnerMetafield($ownerId: ID!, $namespace: String!, $key: String!) {
      node(id: $ownerId) {
        id
        ... on HasMetafields {
          metafield(namespace: $namespace, key: $key) {
            id
            namespace
            key
            type
            value
          }
        }
      }
    }
    GRAPHQL;

    private const METAFIELDS_SET_MUTATION = <<<'GRAPHQL'
    mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
      metafieldsSet(metafields: $metafields) {
        metafields {
          id
          namespace
          key
          type
          value
        }
        userErrors {
          field
          message
          code
        }
      }
    }
    GRAPHQL;

    private ShopifyAdminApi $api;

    public function __construct(ShopifyAdminApi $api)
    {
        $this->api = $api;
    }

    public function forShop(ShopModel|User|string|null $shop = null): self
    {
        $instance = clone $this;
        $instance->api = $this->api->forShop($shop);

        return $instance;
    }

    /**
     * List metafields for any HasMetafields owner (Shop, Product, etc.).
     *
     * @return list<array<string, mixed>>
     */
    public function listForOwner(string $ownerId, ?string $namespace = null, int $first = 20): array
    {
        $first = max(1, min($first, 50));

        $decoded = $this->api->graph(self::OWNER_METAFIELDS_QUERY, [
            'ownerId' => $ownerId,
            'namespace' => $namespace,
            'first' => $first,
        ]);

        $node = $decoded['data']['node'] ?? null;

        if ($node === null) {
            throw new ShopifyGraphQlException(
                'Metafield owner not found.',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
                context: ['ownerId' => $ownerId],
            );
        }

        if (! is_array($node) || ! array_key_exists('metafields', $node)) {
            throw new ShopifyGraphQlException(
                'Owner does not support metafields (missing HasMetafields).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['ownerId' => $ownerId],
            );
        }

        return collect($node['metafields']['edges'] ?? [])
            ->map(fn (array $edge): array => $edge['node'] ?? [])
            ->filter(fn (array $node): bool => $node !== [])
            ->values()
            ->all();
    }

    /**
     * Read a single metafield by owner + namespace + key.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $ownerId, string $namespace, string $key): ?array
    {
        $decoded = $this->api->graph(self::OWNER_METAFIELD_QUERY, [
            'ownerId' => $ownerId,
            'namespace' => $namespace,
            'key' => $key,
        ]);

        $node = $decoded['data']['node'] ?? null;

        if ($node === null) {
            throw new ShopifyGraphQlException(
                'Metafield owner not found.',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
                context: ['ownerId' => $ownerId],
            );
        }

        if (! is_array($node) || ! array_key_exists('metafield', $node)) {
            throw new ShopifyGraphQlException(
                'Owner does not support metafields (missing HasMetafields).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['ownerId' => $ownerId],
            );
        }

        $metafield = $node['metafield'] ?? null;

        return is_array($metafield) ? $metafield : null;
    }

    /**
     * Create or update one or more metafields via metafieldsSet.
     *
     * Each item requires: ownerId, namespace, key, type, value.
     *
     * @param  list<array<string, mixed>>  $metafields
     * @return list<array<string, mixed>>
     */
    public function set(array $metafields): array
    {
        if ($metafields === []) {
            throw new ShopifyGraphQlException(
                'At least one metafield is required.',
                errorCode: ShopifyGraphQlException::CODE_INVALID_INPUT,
            );
        }

        $decoded = $this->api->graph(self::METAFIELDS_SET_MUTATION, [
            'metafields' => $metafields,
        ]);

        $payload = $decoded['data']['metafieldsSet'] ?? null;

        if (! is_array($payload)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.metafieldsSet).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
            );
        }

        $this->throwIfUserErrors(
            is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [],
            $decoded,
            ['operation' => 'metafieldsSet'],
        );

        return collect($payload['metafields'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->values()
            ->all();
    }

    /**
     * Convenience wrapper for a single metafieldsSet entry.
     *
     * @return array<string, mixed>
     */
    public function setOne(
        string $ownerId,
        string $namespace,
        string $key,
        string $value,
        string $type = 'single_line_text_field',
    ): array {
        $result = $this->set([[
            'ownerId' => $ownerId,
            'namespace' => $namespace,
            'key' => $key,
            'type' => $type,
            'value' => $value,
        ]]);

        $metafield = $result[0] ?? null;

        if (! is_array($metafield)) {
            throw new ShopifyGraphQlException(
                'metafieldsSet succeeded without returning a metafield.',
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: [
                    'ownerId' => $ownerId,
                    'namespace' => $namespace,
                    'key' => $key,
                ],
            );
        }

        return $metafield;
    }
}
