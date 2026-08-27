<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\Concerns\AssertsShopifyUserErrors;
use Osiset\ShopifyApp\Contracts\ShopModel;

/**
 * Reusable Admin GraphQL helpers for Shopify metaobjects.
 */
class ShopifyMetaobjectService
{
    use AssertsShopifyUserErrors;

    private const METAOBJECTS_QUERY = <<<'GRAPHQL'
    query Metaobjects($type: String!, $first: Int!) {
      metaobjects(type: $type, first: $first) {
        edges {
          node {
            id
            type
            handle
            updatedAt
            fields {
              key
              value
              type
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

    private const METAOBJECT_QUERY = <<<'GRAPHQL'
    query Metaobject($id: ID!) {
      metaobject(id: $id) {
        id
        type
        handle
        updatedAt
        fields {
          key
          value
          type
        }
      }
    }
    GRAPHQL;

    private const METAOBJECT_CREATE_MUTATION = <<<'GRAPHQL'
    mutation MetaobjectCreate($metaobject: MetaobjectCreateInput!) {
      metaobjectCreate(metaobject: $metaobject) {
        metaobject {
          id
          type
          handle
          updatedAt
          fields {
            key
            value
            type
          }
        }
        userErrors {
          field
          message
          code
        }
      }
    }
    GRAPHQL;

    private const METAOBJECT_UPDATE_MUTATION = <<<'GRAPHQL'
    mutation MetaobjectUpdate($id: ID!, $metaobject: MetaobjectUpdateInput!) {
      metaobjectUpdate(id: $id, metaobject: $metaobject) {
        metaobject {
          id
          type
          handle
          updatedAt
          fields {
            key
            value
            type
          }
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
     * @return array{
     *     metaobjects: list<array<string, mixed>>,
     *     hasNextPage: bool,
     *     endCursor: ?string
     * }
     */
    public function list(string $type, int $first = 10): array
    {
        $first = max(1, min($first, 50));

        $decoded = $this->api->graph(self::METAOBJECTS_QUERY, [
            'type' => $type,
            'first' => $first,
        ]);

        $connection = $decoded['data']['metaobjects'] ?? null;

        if (! is_array($connection)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.metaobjects).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['type' => $type],
            );
        }

        $nodes = collect($connection['edges'] ?? [])
            ->map(fn (array $edge): array => $edge['node'] ?? [])
            ->filter(fn (array $node): bool => $node !== [])
            ->values()
            ->all();

        return [
            'metaobjects' => $nodes,
            'hasNextPage' => (bool) ($connection['pageInfo']['hasNextPage'] ?? false),
            'endCursor' => $connection['pageInfo']['endCursor'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $id): array
    {
        $decoded = $this->api->graph(self::METAOBJECT_QUERY, [
            'id' => $id,
        ]);

        $metaobject = $decoded['data']['metaobject'] ?? null;

        if (! is_array($metaobject)) {
            throw new ShopifyGraphQlException(
                'Metaobject not found.',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
                context: ['id' => $id],
            );
        }

        return $metaobject;
    }

    /**
     * Create a metaobject entry. A matching MetaobjectDefinition must already exist.
     *
     * @param  array<string, mixed>  $input  MetaobjectCreateInput (type required; fields/handle optional)
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        if (! isset($input['type']) || ! is_string($input['type']) || $input['type'] === '') {
            throw new ShopifyGraphQlException(
                'Metaobject type is required.',
                errorCode: ShopifyGraphQlException::CODE_INVALID_INPUT,
            );
        }

        $decoded = $this->api->graph(self::METAOBJECT_CREATE_MUTATION, [
            'metaobject' => $input,
        ]);

        $payload = $decoded['data']['metaobjectCreate'] ?? null;

        if (! is_array($payload)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.metaobjectCreate).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
            );
        }

        $this->throwIfUserErrors(
            is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [],
            $decoded,
            ['operation' => 'metaobjectCreate', 'type' => $input['type']],
        );

        $metaobject = $payload['metaobject'] ?? null;

        if (! is_array($metaobject)) {
            throw new ShopifyGraphQlException(
                'metaobjectCreate succeeded without returning a metaobject.',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['type' => $input['type']],
            );
        }

        return $metaobject;
    }

    /**
     * Update a metaobject. Prefer `fields` for partial updates (patch).
     * Using `values` replaces the full map and clears omitted keys.
     *
     * @param  array<string, mixed>  $input  MetaobjectUpdateInput
     * @return array<string, mixed>
     */
    public function update(string $id, array $input): array
    {
        if ($input === []) {
            throw new ShopifyGraphQlException(
                'Metaobject update input is required.',
                errorCode: ShopifyGraphQlException::CODE_INVALID_INPUT,
                context: ['id' => $id],
            );
        }

        $decoded = $this->api->graph(self::METAOBJECT_UPDATE_MUTATION, [
            'id' => $id,
            'metaobject' => $input,
        ]);

        $payload = $decoded['data']['metaobjectUpdate'] ?? null;

        if (! is_array($payload)) {
            throw new ShopifyGraphQlException(
                'Unexpected GraphQL response shape (missing data.metaobjectUpdate).',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['id' => $id],
            );
        }

        $this->throwIfUserErrors(
            is_array($payload['userErrors'] ?? null) ? $payload['userErrors'] : [],
            $decoded,
            ['operation' => 'metaobjectUpdate', 'id' => $id],
        );

        $metaobject = $payload['metaobject'] ?? null;

        if (! is_array($metaobject)) {
            throw new ShopifyGraphQlException(
                'metaobjectUpdate succeeded without returning a metaobject.',
                response: $decoded,
                errorCode: ShopifyGraphQlException::CODE_UNEXPECTED,
                context: ['id' => $id],
            );
        }

        return $metaobject;
    }
}
