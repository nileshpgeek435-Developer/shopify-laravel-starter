<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Osiset\ShopifyApp\Util;

/**
 * Verify webhook HMAC middleware and that AppUninstalledJob is queued + processable.
 */
class VerifyShopifyWebhooksCommand extends Command
{
    protected $signature = 'shopify:verify-webhooks
                            {--process : Also run queue:work until empty after a valid webhook}';

    protected $description = 'Verify Shopify webhook HMAC auth and database queue wiring';

    public function handle(HttpKernel $http): int
    {
        $secret = (string) Util::getShopifyConfig('api_secret');
        if ($secret === '') {
            $this->error('SHOPIFY_API_SECRET is empty; cannot verify HMAC.');

            return self::FAILURE;
        }

        $this->line('Queue connection: <info>'.config('queue.default').'</info>');
        $this->line('Webhook job queue: <info>'.(config('shopify-app.job_queues.webhooks') ?: 'default').'</info>');

        $body = json_encode([
            'id' => 999001,
            'name' => 'HMAC Verify Shop',
            'myshopify_domain' => 'hmac-verify.myshopify.com',
            'domain' => 'hmac-verify.myshopify.com',
        ], JSON_THROW_ON_ERROR);

        $shop = 'hmac-verify.myshopify.com';
        $validHmac = $this->sign($body, $secret);

        $checks = [
            ['label' => 'missing HMAC', 'shop' => $shop, 'hmac' => null, 'expect' => 401],
            ['label' => 'invalid HMAC', 'shop' => $shop, 'hmac' => 'not-a-valid-signature', 'expect' => 401],
            ['label' => 'missing shop domain', 'shop' => null, 'hmac' => $validHmac, 'expect' => 401],
            ['label' => 'valid HMAC', 'shop' => $shop, 'hmac' => $validHmac, 'expect' => 201],
        ];

        foreach ($checks as $check) {
            $status = $this->postWebhook($http, $body, $check['shop'], $check['hmac']);
            if ($status !== $check['expect']) {
                $this->error("{$check['label']}: expected HTTP {$check['expect']}, got {$status}");

                return self::FAILURE;
            }
            $this->info("OK  {$check['label']} → HTTP {$status}");
        }

        $pending = DB::table('jobs')->count();
        $this->line("Pending jobs after valid webhook: <info>{$pending}</info>");

        if ($pending < 1) {
            $this->error('Expected at least one queued job after a valid webhook POST.');

            return self::FAILURE;
        }

        if ($this->option('process')) {
            $this->line('Processing queue until empty…');
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => 25,
                '--no-interaction' => true,
            ]);
            $this->output->write(Artisan::output());

            $remaining = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->line("Remaining jobs: <info>{$remaining}</info>");
            $this->line("Failed jobs: <info>{$failed}</info>");

            if ($remaining > 0) {
                $this->error('Queue still has pending jobs after processing.');

                return self::FAILURE;
            }
        } else {
            $this->comment('Tip: re-run with --process to drain the queue (or start the compose `queue` service).');
        }

        $this->info('Webhook HMAC + queue wiring looks good.');

        return self::SUCCESS;
    }

    private function sign(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    private function postWebhook(HttpKernel $http, string $body, ?string $shop, ?string $hmac): int
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($shop !== null) {
            $server['HTTP_X_SHOPIFY_SHOP_DOMAIN'] = $shop;
        }

        if ($hmac !== null) {
            $server['HTTP_X_SHOPIFY_HMAC_SHA256'] = $hmac;
        }

        $request = Request::create(
            '/webhook/app-uninstalled',
            'POST',
            [],
            [],
            [],
            $server,
            $body
        );

        $response = $http->handle($request);
        $status = $response->getStatusCode();
        $http->terminate($request, $response);

        return $status;
    }
}
