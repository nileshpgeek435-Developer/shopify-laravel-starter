<?php

namespace Tests\Unit;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\ShopifyAdminApi;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Mockery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyAdminApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_shop_data_from_graphql(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'shop' => [
                        'id' => 'gid://shopify/Shop/1',
                        'name' => 'Demo Shop',
                        'myshopifyDomain' => 'demo.myshopify.com',
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyAdminApi::class)->forShop($shop)->getShop();

        $this->assertSame('Demo Shop', $result['name']);
        $this->assertSame('demo.myshopify.com', $result['myshopifyDomain']);
    }

    #[Test]
    public function it_returns_products_from_graphql(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'products' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Product/1',
                                    'title' => 'Snowboard',
                                    'handle' => 'snowboard',
                                    'status' => 'ACTIVE',
                                    'totalInventory' => 10,
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

        $result = app(ShopifyAdminApi::class)->forShop($shop)->getProducts(5);

        $this->assertCount(1, $result['products']);
        $this->assertSame('Snowboard', $result['products'][0]['title']);
        $this->assertFalse($result['hasNextPage']);
    }

    #[Test]
    public function it_throws_when_shop_has_no_offline_token(): void
    {
        $shop = Mockery::mock(User::class);
        $shop->shouldReceive('hasOfflineAccess')->andReturn(false);
        $shop->shouldReceive('getDomain')->andReturn(ShopDomain::fromNative('demo.myshopify.com'));

        $this->expectException(ShopifyGraphQlException::class);
        $this->expectExceptionMessage('Shop has no offline access token');

        app(ShopifyAdminApi::class)->forShop($shop)->getShop();
    }

    #[Test]
    public function it_throws_access_denied_for_graphql_errors(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'errors' => [
                    [
                        'message' => 'Access denied for products field.',
                        'extensions' => ['code' => 'ACCESS_DENIED'],
                    ],
                ],
                'data' => null,
            ],
        ]);

        try {
            app(ShopifyAdminApi::class)->forShop($shop)->getProducts();
            $this->fail('Expected ShopifyGraphQlException was not thrown.');
        } catch (ShopifyGraphQlException $e) {
            $this->assertTrue($e->isAccessDenied());
            $this->assertSame(ShopifyGraphQlException::CODE_ACCESS_DENIED, $e->errorCode);
            $this->assertSame(403, $e->httpStatus());
        }
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
