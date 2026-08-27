<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust ngrok / reverse proxies so HTTPS and host are detected correctly
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\App\Exceptions\ShopifyGraphQlException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => $e->toArray(),
                ], $e->httpStatus());
            }

            return null;
        });

        $exceptions->render(function (\App\Exceptions\ShopifyBillingException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => $e->toArray(),
                ], $e->httpStatus());
            }

            return null;
        });

        // Visiting /authenticate without ?shop= throws from laravel-shopify — return 400, not 500.
        $exceptions->render(function (
            \Osiset\ShopifyApp\Exceptions\MissingShopDomainException $e,
            Request $request
        ) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'message' => 'Shop domain required. Pass ?shop=YOUR_STORE.myshopify.com',
                        'code' => 'missing_shop_domain',
                    ],
                ], 400);
            }

            return response()->view('errors.missing-shop-domain', [
                'message' => $e->getMessage(),
            ], 400);
        });
    })->create();
