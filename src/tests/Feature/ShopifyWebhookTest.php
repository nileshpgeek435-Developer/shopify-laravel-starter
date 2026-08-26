<?php

namespace Tests\Feature;

use App\Jobs\AppUninstalledJob;
use App\Listeners\HandleAppUninstalled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Contracts\Commands\Shop as ShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as ShopQuery;
use Osiset\ShopifyApp\Messaging\Events\AppUninstalledEvent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const API_SECRET = 'test-shopify-webhook-secret';

    private const SHOP_DOMAIN = 'demo.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopify-app.api_secret' => self::API_SECRET,
            'shopify-app.webhooks' => [
                'app-uninstalled' => [
                    'topic' => 'APP_UNINSTALLED',
                    'address' => 'https://example.test/webhook/app-uninstalled',
                    'class' => AppUninstalledJob::class,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_rejects_webhook_without_hmac(): void
    {
        Bus::fake();

        $response = $this->postWebhook(self::SHOP_DOMAIN, $this->uninstallPayload(), hmac: null);

        $response->assertUnauthorized();
        Bus::assertNotDispatched(AppUninstalledJob::class);
    }

    #[Test]
    public function it_rejects_webhook_with_invalid_hmac(): void
    {
        Bus::fake();

        $response = $this->postWebhook(
            self::SHOP_DOMAIN,
            $this->uninstallPayload(),
            hmac: 'not-a-valid-signature'
        );

        $response->assertUnauthorized();
        Bus::assertNotDispatched(AppUninstalledJob::class);
    }

    #[Test]
    public function it_rejects_webhook_without_shop_domain(): void
    {
        Bus::fake();
        $body = $this->uninstallPayload();

        $response = $this->postWebhook(null, $body, hmac: $this->sign($body));

        $response->assertUnauthorized();
        Bus::assertNotDispatched(AppUninstalledJob::class);
    }

    #[Test]
    public function it_accepts_valid_hmac_and_dispatches_app_uninstalled_job(): void
    {
        Bus::fake();
        $body = $this->uninstallPayload();

        $response = $this->postWebhook(
            self::SHOP_DOMAIN,
            $body,
            hmac: $this->sign($body)
        );

        $response->assertCreated();
        Bus::assertDispatched(AppUninstalledJob::class);
    }

    #[Test]
    public function it_rejects_get_on_webhook_route(): void
    {
        $this->get('/webhook/app-uninstalled')->assertMethodNotAllowed();
    }

    #[Test]
    public function app_uninstalled_job_cleans_and_soft_deletes_shop(): void
    {
        Event::fake([AppUninstalledEvent::class]);

        $shop = User::query()->create([
            'name' => self::SHOP_DOMAIN,
            'email' => 'shop@demo.myshopify.com',
            'password' => 'shpat_offline_token_for_tests',
            'shopify_grandfathered' => false,
            'shopify_freemium' => false,
            'plan_id' => null,
        ]);

        $job = new AppUninstalledJob(
            self::SHOP_DOMAIN,
            (object) json_decode($this->uninstallPayload(), false, 512, JSON_THROW_ON_ERROR)
        );

        $result = $job->handle(
            app(ShopCommand::class),
            app(ShopQuery::class),
            app(CancelCurrentPlan::class)
        );

        $this->assertTrue($result);

        $this->assertSoftDeleted('users', ['id' => $shop->id]);

        $trashed = User::withTrashed()->findOrFail($shop->id);
        $this->assertSame('', (string) $trashed->password);
        $this->assertNull($trashed->plan_id);

        Event::assertDispatched(AppUninstalledEvent::class);
    }

    #[Test]
    public function app_uninstalled_job_is_noop_when_shop_missing(): void
    {
        Event::fake([AppUninstalledEvent::class]);

        $job = new AppUninstalledJob(
            'missing-shop.myshopify.com',
            (object) ['id' => 0]
        );

        $result = $job->handle(
            app(ShopCommand::class),
            app(ShopQuery::class),
            app(CancelCurrentPlan::class)
        );

        $this->assertTrue($result);
        Event::assertNotDispatched(AppUninstalledEvent::class);
    }

    #[Test]
    public function handle_app_uninstalled_listener_is_registered(): void
    {
        Event::fake();

        Event::assertListening(
            AppUninstalledEvent::class,
            HandleAppUninstalled::class
        );
    }

    private function uninstallPayload(): string
    {
        return json_encode([
            'id' => 123456789,
            'name' => 'Demo Shop',
            'email' => 'shop@demo.myshopify.com',
            'domain' => self::SHOP_DOMAIN,
            'myshopify_domain' => self::SHOP_DOMAIN,
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, self::API_SECRET, true));
    }

    private function postWebhook(?string $shopDomain, string $body, ?string $hmac)
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($shopDomain !== null) {
            $server['HTTP_X_SHOPIFY_SHOP_DOMAIN'] = $shopDomain;
        }

        if ($hmac !== null) {
            $server['HTTP_X_SHOPIFY_HMAC_SHA256'] = $hmac;
        }

        return $this->call(
            'POST',
            '/webhook/app-uninstalled',
            [],
            [],
            [],
            $server,
            $body
        );
    }
}
