<?php

namespace Tests\Feature;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function shop_endpoint_returns_shop_json(): void
    {
        $user = $this->makeShopUser();

        $api = Mockery::mock(ShopifyAdminApi::class);
        $api->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $api->shouldReceive('getShop')->once()->andReturn([
            'name' => 'Demo Shop',
            'myshopifyDomain' => 'demo.myshopify.com',
        ]);
        $this->app->instance(ShopifyAdminApi::class, $api);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/shop');

        $response->assertOk()
            ->assertJsonPath('shop.name', 'Demo Shop');
    }

    #[Test]
    public function products_endpoint_returns_products_json(): void
    {
        $user = $this->makeShopUser();

        $api = Mockery::mock(ShopifyAdminApi::class);
        $api->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $api->shouldReceive('getProducts')->once()->with(3)->andReturn([
            'products' => [
                ['id' => 'gid://shopify/Product/1', 'title' => 'Snowboard'],
            ],
            'hasNextPage' => false,
            'endCursor' => null,
        ]);
        $this->app->instance(ShopifyAdminApi::class, $api);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/products?first=3');

        $response->assertOk()
            ->assertJsonPath('products.0.title', 'Snowboard')
            ->assertJsonPath('hasNextPage', false);
    }

    #[Test]
    public function products_endpoint_maps_access_denied_to_403(): void
    {
        $user = $this->makeShopUser();

        $api = Mockery::mock(ShopifyAdminApi::class);
        $api->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $api->shouldReceive('getProducts')->once()->andThrow(new ShopifyGraphQlException(
            'Access denied for products field.',
            errorCode: ShopifyGraphQlException::CODE_ACCESS_DENIED,
        ));
        $this->app->instance(ShopifyAdminApi::class, $api);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/products');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'access_denied');
    }

    #[Test]
    public function dashboard_receives_shop_and_products_props(): void
    {
        $user = $this->makeShopUser();

        $api = Mockery::mock(ShopifyAdminApi::class);
        $api->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $api->shouldReceive('getShop')->once()->andReturn([
            'name' => 'Demo Shop',
            'myshopifyDomain' => 'demo.myshopify.com',
            'plan' => ['displayName' => 'Development', 'partnerDevelopment' => true],
        ]);
        $api->shouldReceive('getProducts')->once()->with(10)->andReturn([
            'products' => [
                ['id' => 'gid://shopify/Product/1', 'title' => 'Snowboard', 'status' => 'ACTIVE', 'totalInventory' => 5],
            ],
            'hasNextPage' => true,
            'endCursor' => 'cursor',
        ]);
        $this->app->instance(ShopifyAdminApi::class, $api);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('shop.name', 'Demo Shop')
            ->where('products.0.title', 'Snowboard')
            ->where('hasNextPage', true)
            ->where('error', null));
    }

    private function makeShopUser(): User
    {
        return User::query()->create([
            'name' => 'demo.myshopify.com',
            'email' => 'shop@demo.myshopify.com',
            'password' => 'shpat_test_token_1234567890',
            'shopify_grandfathered' => false,
            'shopify_freemium' => false,
        ]);
    }
}
