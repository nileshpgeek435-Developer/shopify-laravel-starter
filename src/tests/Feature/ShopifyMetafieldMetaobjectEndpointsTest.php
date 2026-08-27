<?php

namespace Tests\Feature;

use App\Exceptions\ShopifyGraphQlException;
use App\Models\User;
use App\Services\Shopify\ShopifyMetafieldService;
use App\Services\Shopify\ShopifyMetaobjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyMetafieldMetaobjectEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function metafields_index_returns_json(): void
    {
        $user = $this->makeShopUser();

        $service = Mockery::mock(ShopifyMetafieldService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('listForOwner')
            ->once()
            ->with('gid://shopify/Shop/1', 'custom', 20)
            ->andReturn([
                [
                    'id' => 'gid://shopify/Metafield/1',
                    'namespace' => 'custom',
                    'key' => 'note',
                    'type' => 'single_line_text_field',
                    'value' => 'hello',
                ],
            ]);
        $this->app->instance(ShopifyMetafieldService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/metafields?owner_id='.urlencode('gid://shopify/Shop/1').'&namespace=custom');

        $response->assertOk()
            ->assertJsonPath('metafields.0.key', 'note');
    }

    #[Test]
    public function metafields_store_returns_created_metafield(): void
    {
        $user = $this->makeShopUser();

        $service = Mockery::mock(ShopifyMetafieldService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('setOne')
            ->once()
            ->with(
                'gid://shopify/Product/1',
                'custom',
                'material',
                'cotton',
                'single_line_text_field',
            )
            ->andReturn([
                'id' => 'gid://shopify/Metafield/2',
                'namespace' => 'custom',
                'key' => 'material',
                'type' => 'single_line_text_field',
                'value' => 'cotton',
            ]);
        $this->app->instance(ShopifyMetafieldService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->postJson('/api/metafields', [
                'owner_id' => 'gid://shopify/Product/1',
                'namespace' => 'custom',
                'key' => 'material',
                'value' => 'cotton',
            ]);

        $response->assertCreated()
            ->assertJsonPath('metafield.value', 'cotton');
    }

    #[Test]
    public function metafields_index_requires_owner_id(): void
    {
        $user = $this->makeShopUser();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/metafields');

        $response->assertStatus(422);
    }

    #[Test]
    public function metafields_maps_graphql_errors(): void
    {
        $user = $this->makeShopUser();

        $service = Mockery::mock(ShopifyMetafieldService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('listForOwner')->once()->andThrow(new ShopifyGraphQlException(
            'Access denied for metafields field.',
            errorCode: ShopifyGraphQlException::CODE_ACCESS_DENIED,
        ));
        $this->app->instance(ShopifyMetafieldService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/metafields?owner_id='.urlencode('gid://shopify/Shop/1'));

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'access_denied');
    }

    #[Test]
    public function metaobjects_index_returns_json(): void
    {
        $user = $this->makeShopUser();

        $service = Mockery::mock(ShopifyMetaobjectService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('list')->once()->with('size_chart', 10)->andReturn([
            'metaobjects' => [
                ['id' => 'gid://shopify/Metaobject/1', 'handle' => 'default', 'type' => 'size_chart'],
            ],
            'hasNextPage' => false,
            'endCursor' => null,
        ]);
        $this->app->instance(ShopifyMetaobjectService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/metaobjects?type=size_chart');

        $response->assertOk()
            ->assertJsonPath('metaobjects.0.handle', 'default');
    }

    #[Test]
    public function metaobjects_show_returns_json(): void
    {
        $user = $this->makeShopUser();
        $id = 'gid://shopify/Metaobject/1';

        $service = Mockery::mock(ShopifyMetaobjectService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('find')->once()->with($id)->andReturn([
            'id' => $id,
            'type' => 'size_chart',
            'handle' => 'default',
        ]);
        $this->app->instance(ShopifyMetaobjectService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/metaobjects/'.rawurlencode($id));

        $response->assertOk()
            ->assertJsonPath('metaobject.handle', 'default');
    }

    #[Test]
    public function metaobjects_store_creates_entry(): void
    {
        $user = $this->makeShopUser();

        $service = Mockery::mock(ShopifyMetaobjectService::class);
        $service->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $service->shouldReceive('create')
            ->once()
            ->with([
                'type' => 'size_chart',
                'handle' => 'winter',
                'fields' => [
                    ['key' => 'title', 'value' => 'Winter'],
                ],
            ])
            ->andReturn([
                'id' => 'gid://shopify/Metaobject/9',
                'type' => 'size_chart',
                'handle' => 'winter',
            ]);
        $this->app->instance(ShopifyMetaobjectService::class, $service);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->postJson('/api/metaobjects', [
                'type' => 'size_chart',
                'handle' => 'winter',
                'fields' => [
                    ['key' => 'title', 'value' => 'Winter'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('metaobject.handle', 'winter');
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
