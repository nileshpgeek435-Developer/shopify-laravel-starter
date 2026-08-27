<?php

namespace Tests\Unit;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\ShopifyMetafieldService;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Mockery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyMetafieldServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_lists_metafields_for_an_owner(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'node' => [
                        'id' => 'gid://shopify/Shop/1',
                        'metafields' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/Metafield/1',
                                        'namespace' => 'custom',
                                        'key' => 'note',
                                        'type' => 'single_line_text_field',
                                        'value' => 'hello',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyMetafieldService::class)
            ->forShop($shop)
            ->listForOwner('gid://shopify/Shop/1', 'custom', 10);

        $this->assertCount(1, $result);
        $this->assertSame('note', $result[0]['key']);
        $this->assertSame('hello', $result[0]['value']);
    }

    #[Test]
    public function it_sets_metafields_via_mutation(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [
                            [
                                'id' => 'gid://shopify/Metafield/9',
                                'namespace' => 'custom',
                                'key' => 'note',
                                'type' => 'single_line_text_field',
                                'value' => 'updated',
                            ],
                        ],
                        'userErrors' => [],
                    ],
                ],
            ],
        ]);

        $result = app(ShopifyMetafieldService::class)
            ->forShop($shop)
            ->setOne(
                'gid://shopify/Shop/1',
                'custom',
                'note',
                'updated',
            );

        $this->assertSame('updated', $result['value']);
        $this->assertSame('custom', $result['namespace']);
    }

    #[Test]
    public function it_throws_invalid_input_for_user_errors(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => false,
            'status' => 200,
            'body' => [
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [],
                        'userErrors' => [
                            [
                                'field' => ['metafields', '0', 'key'],
                                'message' => 'Key is invalid',
                                'code' => 'INVALID',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            app(ShopifyMetafieldService::class)
                ->forShop($shop)
                ->setOne('gid://shopify/Shop/1', 'custom', 'bad key', 'x');
            $this->fail('Expected ShopifyGraphQlException was not thrown.');
        } catch (ShopifyGraphQlException $e) {
            $this->assertSame(ShopifyGraphQlException::CODE_INVALID_INPUT, $e->errorCode);
            $this->assertSame(422, $e->httpStatus());
            $this->assertStringContainsString('Key is invalid', $e->getMessage());
        }
    }

    #[Test]
    public function it_throws_on_transport_failures(): void
    {
        $shop = $this->mockShopWithGraphResponse([
            'errors' => true,
            'status' => 502,
            'body' => 'Bad Gateway',
        ]);

        $this->expectException(ShopifyGraphQlException::class);

        app(ShopifyMetafieldService::class)
            ->forShop($shop)
            ->listForOwner('gid://shopify/Shop/1');
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
