<?php

namespace App\Http\Controllers;

use App\Exceptions\ShopifyBillingException;
use App\Services\Shopify\ShopifyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Osiset\ShopifyApp\Contracts\ShopModel;

class BillingController extends Controller
{
    public function __construct(
        protected ShopifyBillingService $billing,
    ) {}

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $state = $this->billing->getBillingState($shop);
        $flash = $this->billingFlash($request);

        return Inertia::render('Billing', [
            'billing' => $state,
            'plans' => $this->billing->listAvailablePlans($shop),
            'shopDomain' => $shop->getDomain()->toNative(),
            'host' => $request->get('host'),
            'locale' => $request->get('locale'),
            'flash' => $flash,
            'error' => null,
        ]);
    }

    public function subscribe(Request $request, int $plan): RedirectResponse
    {
        $shop = $this->shop($request);
        $host = (string) ($request->get('host') ?? '');

        try {
            $this->billing->assertPaidPlanExists($plan);

            return redirect()->route('billing', array_filter([
                'plan' => $plan,
                'shop' => $shop->getDomain()->toNative(),
                'host' => $host !== '' ? $host : null,
                'locale' => $request->get('locale'),
            ]));
        } catch (ShopifyBillingException $e) {
            Log::notice('Billing subscribe blocked', $e->toArray());

            return redirect()
                ->route('billing.index', $this->shopQuery($request, $shop))
                ->with('billing_error', $e->toArray());
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);

        try {
            $this->billing->cancelSubscription($shop);

            return redirect()
                ->route('billing.index', $this->shopQuery($request, $shop))
                ->with('billing_success', 'Subscription cancelled. You are on the Free plan.');
        } catch (ShopifyBillingException $e) {
            Log::notice('Billing cancel failed', $e->toArray());

            return redirect()
                ->route('billing.index', $this->shopQuery($request, $shop))
                ->with('billing_error', $e->toArray());
        }
    }

    public function status(Request $request)
    {
        $shop = $this->shop($request);

        return response()->json([
            'billing' => $this->billing->getBillingState($shop),
            'plans' => $this->billing->listAvailablePlans($shop),
        ]);
    }

    private function shop(Request $request): ShopModel
    {
        $user = $request->user();

        if (! $user instanceof ShopModel) {
            abort(401, 'Unauthenticated shop.');
        }

        return $user;
    }

    /**
     * @return array{shop: string, host?: mixed, locale?: mixed}
     */
    private function shopQuery(Request $request, ShopModel $shop): array
    {
        return array_filter([
            'shop' => $shop->getDomain()->toNative(),
            'host' => $request->get('host'),
            'locale' => $request->get('locale'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private function billingFlash(Request $request): ?array
    {
        if ($request->hasSession()) {
            if ($request->session()->has('billing_success')) {
                return [
                    'type' => 'success',
                    'message' => (string) $request->session()->get('billing_success'),
                ];
            }

            if ($request->session()->has('billing_error')) {
                $error = $request->session()->get('billing_error');
                $message = is_array($error)
                    ? (string) ($error['message'] ?? 'Billing error')
                    : (string) $error;

                return [
                    'type' => 'error',
                    'message' => $message,
                ];
            }
        }

        // Package billing.process redirects home with billing=success|failure; support same on this page.
        $billing = $request->query('billing');
        if ($billing === 'success') {
            return ['type' => 'success', 'message' => 'Billing confirmed. Your plan is now active.'];
        }
        if ($billing === 'failure') {
            return ['type' => 'error', 'message' => 'Billing confirmation failed or was declined.'];
        }

        return null;
    }
}
