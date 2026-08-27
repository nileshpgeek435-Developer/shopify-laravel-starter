<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Shopify\ShopifyAdminApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Osiset\ShopifyApp\Http\Middleware\VerifyShopify;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyEmbeddedContextTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function inertia_shares_public_shopify_context_without_secrets(): void
    {
        config([
            'shopify-app.api_key' => 'public-client-id-only',
            'shopify-app.api_secret' => 'must-never-appear-in-inertia',
            'shopify-app.frontend_type' => 'SPA',
        ]);

        $user = User::query()->create([
            'name' => 'demo.myshopify.com',
            'email' => 'shop@demo.myshopify.com',
            'password' => 'shpat_offline_token_for_tests',
            'shopify_grandfathered' => false,
            'shopify_freemium' => true,
        ]);

        $api = Mockery::mock(ShopifyAdminApi::class);
        $api->shouldReceive('forShop')->once()->with($user)->andReturnSelf();
        $api->shouldReceive('getShop')->once()->andReturn([
            'name' => 'Demo Shop',
            'myshopifyDomain' => 'demo.myshopify.com',
        ]);
        $api->shouldReceive('getProducts')->once()->with(10)->andReturn([
            'products' => [],
            'hasNextPage' => false,
            'endCursor' => null,
        ]);
        $this->app->instance(ShopifyAdminApi::class, $api);

        // Keep HandleInertiaRequests (web group) so shared shopify props are present.
        $response = $this
            ->withoutMiddleware([VerifyShopify::class])
            ->actingAs($user)
            ->get('/?shop=demo.myshopify.com&host=abc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('shopify.apiKey', 'public-client-id-only')
            ->where('shopify.shopDomain', 'demo.myshopify.com')
            ->where('shopify.host', 'abc')
            ->where('shopify.frontendType', 'SPA')
            ->where('shopify.embedded', true)
            ->missing('shopify.apiSecret')
            ->missing('shopify.api_secret')
            ->missing('shopify.accessToken')
            ->missing('shopify.password'));

        $content = $response->getContent();
        $this->assertStringNotContainsString('must-never-appear-in-inertia', $content);
        $this->assertStringNotContainsString('shpat_offline_token_for_tests', $content);
        $this->assertStringContainsString('cdn.shopify.com/shopifycloud/app-bridge.js', $content);
        $this->assertStringContainsString('name="shopify-api-key"', $content);
        $this->assertStringContainsString('public-client-id-only', $content);
    }
}
