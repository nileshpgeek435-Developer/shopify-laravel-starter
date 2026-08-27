<?php

namespace Tests\Unit;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\ShopifyMetaobjectService;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Mockery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyMetaobjectServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_lists_metaobjects_by_type(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metaobjects' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Metaobject/1',
                                    'type' => 'size_chart',
                                    'handle' => 'default',
                                    'updatedAt' => '2026-01-01T00:00:00Z',
                                    'fields' => [
                                        ['key' => 'title', 'value' => 'Chart', 'type' => 'single_line_text_field'],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyMetaobjectService::class)
            ->forShop($shop)
            ->list('size_chart', 5);

        $this->assertCount(1, $result['metaobjects']);
        $this->assertSame('default', $result['metaobjects'][0]['handle']);
        $this->assertFalse($result['hasNextPage']);
    }

    #[Test]
    public function it_creates_a_metaobject(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metaobjectCreate' => [
                        'metaobject' => [
                            'id' => 'gid://shopify/Metaobject/2',
                            'type' => 'size_chart',
                            'handle' => 'winter',
                            'updatedAt' => '2026-01-01T00:00:00Z',
                            'fields' => [
                                ['key' => 'title', 'value' => 'Winter', 'type' => 'single_line_text_field'],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyMetaobjectService::class)
            ->forShop($shop)
            ->create([
                'type' => 'size_chart',
                'handle' => 'winter',
                'fields' => [
                    ['key' => 'title', 'value' => 'Winter'],
                ],
            ]);

        $this->assertSame('winter', $result['handle']);
        $this->assertSame('size_chart', $result['type']);
    }

    #[Test]
    public function it_updates_a_metaobject(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metaobjectUpdate' => [
                        'metaobject' => [
                            'id' => 'gid://shopify/Metaobject/2',
                            'type' => 'size_chart',
                            'handle' => 'winter',
                            'updatedAt' => '2026-01-02T00:00:00Z',
                            'fields' => [
                                ['key' => 'title', 'value' => 'Updated', 'type' => 'single_line_text_field'],
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyMetaobjectService::class)
            ->forShop($shop)
            ->update('gid://shopify/Metaobject/2', [
                'fields' => [
                    ['key' => 'title', 'value' => 'Updated'],
                ],
            ]);

        $this->assertSame('Updated', $result['fields'][0]['value']);
    }

    #[Test]
    public function it_throws_invalid_input_for_user_errors(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metaobjectCreate' => [
                        'metaobject' => null,
                        'userErrors' => [
                            [
                                'field' => ['metaobject', 'type'],
                                'message' => 'Type does not exist',
                                'code' => 'UNDEFINED_OBJECT_TYPE',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            app(ShopifyMetaobjectService::class)
                ->forShop($shop)
                ->create(['type' => 'missing_definition']);
            $this->fail('Expected ShopifyGraphQlException was not thrown.');
        } catch (ShopifyGraphQlException $e) {
            $this->assertSame(ShopifyGraphQlException::CODE_INVALID_INPUT, $e->errorCode);
            $this->assertSame(422, $e->httpStatus());
            $this->assertStringContainsString('Type does not exist', $e->getMessage());
        }
    }

    #[Test]
    public function it_throws_on_transport_failures(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => true,
            'status' => 500,
            'body' => 'Internal Server Error',
        ]);

        $this->expectException(ShopifyGraphQlException::class);

        app(ShopifyMetaobjectService::class)
            ->forShop($shop)
            ->find('gid://shopify/Metaobject/1');
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function mockShopWithGraphResponse(array $response): User
    {
        $client = Mockery::mock(BasicShopifyAPI::class);
        $client->shouldReceive('graph')->once()->andReturn($response);

        $shop = Mockery::mock(User::class);
        $shop->shouldReceive('hasOfflineAccess')->andReturn(true);
        $shop->shouldReceive('getDomain')->andReturn(ShopDomain::fromNative('demo.myshopify.com'));
        $shop->shouldReceive('api')->andReturn($client);

        return $shop;
    }
}
