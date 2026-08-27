<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Shopify\ShopifyBillingService;
use Database\Seeders\ShopifyPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Osiset\ShopifyApp\Storage\Models\Plan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyBillingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopify-app.billing_enabled' => true,
            'shopify-app.billing_freemium_enabled' => true,
        ]);

        $this->seed(ShopifyPlanSeeder::class);
    }

    #[Test]
    public function billing_page_receives_plans_and_state_props(): void
    {
        $user = $this->makeShop();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->get('/plans?shop=demo.myshopify.com');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Billing')
            ->has('plans', 3)
            ->where('billing.status', 'free')
            ->where('shopDomain', 'demo.myshopify.com')
            ->where('error', null));
    }

    #[Test]
    public function billing_status_endpoint_returns_json(): void
    {
        $user = $this->makeShop();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->getJson('/api/billing?shop=demo.myshopify.com');

        $response->assertOk()
            ->assertJsonPath('billing.status', 'free')
            ->assertJsonCount(3, 'plans');
    }

    #[Test]
    public function subscribe_redirects_to_package_billing_route(): void
    {
        $user = $this->makeShop();
        $plan = Plan::query()->where('name', 'Basic')->firstOrFail();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->post("/plans/{$plan->id}/subscribe", [
                'shop' => 'demo.myshopify.com',
                'host' => 'abc',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/billing/'.$plan->id, $response->headers->get('Location'));
    }

    #[Test]
    public function subscribe_rejects_unknown_plan(): void
    {
        $user = $this->makeShop();

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->from('/plans?shop=demo.myshopify.com')
            ->post('/plans/999999/subscribe', [
                'shop' => 'demo.myshopify.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('billing_error');
    }

    #[Test]
    public function cancel_moves_shop_to_freemium(): void
    {
        $plan = Plan::query()->where('name', 'Pro')->firstOrFail();
        $user = $this->makeShop(['plan_id' => $plan->id, 'shopify_freemium' => false]);

        $response = $this
            ->withoutMiddleware()
            ->actingAs($user)
            ->post('/plans/cancel', [
                'shop' => 'demo.myshopify.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('billing_success');

        $user->refresh();
        $this->assertNull($user->plan_id);
        $this->assertTrue((bool) $user->shopify_freemium);
        $this->assertFalse(app(ShopifyBillingService::class)->hasActivePaidPlan($user));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeShop(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'demo.myshopify.com',
            'email' => 'shop@demo.myshopify.com',
            'password' => 'shpat_offline_token_for_tests',
            'shopify_grandfathered' => false,
            'shopify_freemium' => true,
            'plan_id' => null,
        ], $overrides));
    }
}
