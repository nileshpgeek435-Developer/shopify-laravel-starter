<?php

namespace Tests\Unit;

use App\Exceptions\ShopifyBillingException;
use App\Models\User;
use App\Services\Shopify\ShopifyBillingService;
use Database\Seeders\ShopifyPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Osiset\ShopifyApp\Actions\GetPlanUrl;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Storage\Models\Plan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyBillingServiceTest extends TestCase
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
    public function it_lists_free_and_seeded_paid_plans(): void
    {
        $plans = app(ShopifyBillingService::class)->listAvailablePlans();

        $names = collect($plans)->pluck('name')->all();

        $this->assertContains('Free', $names);
        $this->assertContains('Basic', $names);
        $this->assertContains('Pro', $names);
        $this->assertSame(3, count($plans));
    }

    #[Test]
    public function it_treats_shop_without_plan_as_free_when_freemium_enabled(): void
    {
        $shop = $this->makeShop();

        $state = app(ShopifyBillingService::class)->getBillingState($shop);

        $this->assertSame('free', $state['status']);
        $this->assertFalse($state['is_paid']);
        $this->assertFalse(app(ShopifyBillingService::class)->hasActivePaidPlan($shop));
    }

    #[Test]
    public function it_detects_active_paid_plan(): void
    {
        $plan = Plan::query()->where('name', 'Basic')->firstOrFail();
        $shop = $this->makeShop(['plan_id' => $plan->id, 'shopify_freemium' => false]);

        $state = app(ShopifyBillingService::class)->getBillingState($shop);

        $this->assertSame('active', $state['status']);
        $this->assertTrue($state['is_paid']);
        $this->assertTrue($state['can_cancel']);
        $this->assertTrue(app(ShopifyBillingService::class)->hasActivePaidPlan($shop));
        $this->assertSame('Basic', $state['current_plan']['name']);
    }

    #[Test]
    public function it_rejects_invalid_plan_for_subscription(): void
    {
        $this->expectException(ShopifyBillingException::class);
        $this->expectExceptionMessage('Invalid or non-billable plan selected.');

        app(ShopifyBillingService::class)->assertPaidPlanExists(999999);
    }

    #[Test]
    public function it_builds_subscription_url_via_package_get_plan_url(): void
    {
        $plan = Plan::query()->where('name', 'Pro')->firstOrFail();
        $shop = $this->makeShop();

        $this->mock(GetPlanUrl::class, function ($mock) use ($shop, $plan) {
            $mock->shouldReceive('__invoke')
                ->once()
                ->withArgs(function ($shopId, $planId, $host) use ($shop, $plan) {
                    return $shopId->toNative() === $shop->id
                        && $planId instanceof NullablePlanId
                        && $planId->toNative() === $plan->id
                        && $host === 'example-host';
                })
                ->andReturn('https://shopify.example/confirm-charge');
        });

        $url = app(ShopifyBillingService::class)->createSubscriptionUrl($shop, (int) $plan->id, 'example-host');

        $this->assertSame('https://shopify.example/confirm-charge', $url);
    }

    #[Test]
    public function it_cancels_local_subscription_and_restores_freemium(): void
    {
        $plan = Plan::query()->where('name', 'Basic')->firstOrFail();
        $shop = $this->makeShop(['plan_id' => $plan->id, 'shopify_freemium' => false]);

        $result = app(ShopifyBillingService::class)->cancelSubscription($shop);

        $this->assertTrue($result);
        $shop->refresh();
        $this->assertNull($shop->plan_id);
        $this->assertTrue((bool) $shop->shopify_freemium);
        $this->assertFalse(app(ShopifyBillingService::class)->hasActivePaidPlan($shop));
    }

    #[Test]
    public function it_fails_cancel_when_no_paid_plan(): void
    {
        $shop = $this->makeShop();

        $this->expectException(ShopifyBillingException::class);

        app(ShopifyBillingService::class)->cancelSubscription($shop);
    }

    #[Test]
    public function it_fails_when_billing_disabled(): void
    {
        config(['shopify-app.billing_enabled' => false]);
        $plan = Plan::query()->where('name', 'Basic')->firstOrFail();

        $this->expectException(ShopifyBillingException::class);

        app(ShopifyBillingService::class)->assertPaidPlanExists((int) $plan->id);
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
