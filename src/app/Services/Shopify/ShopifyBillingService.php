<?php

namespace App\Services\Shopify;

use App\Exceptions\ShopifyBillingException;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Actions\GetPlanUrl;
use Osiset\ShopifyApp\Contracts\Queries\Plan as PlanQuery;
use Osiset\ShopifyApp\Contracts\ShopModel;
use Osiset\ShopifyApp\Objects\Enums\ChargeStatus;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Objects\Values\PlanId;
use Osiset\ShopifyApp\Services\ChargeHelper;
use Osiset\ShopifyApp\Storage\Models\Plan;
use Osiset\ShopifyApp\Util;
use Throwable;

/**
 * Application billing facade over kyon147/laravel-shopify plans/charges.
 */
class ShopifyBillingService
{
    public function __construct(
        protected PlanQuery $planQuery,
        protected ChargeHelper $chargeHelper,
        protected GetPlanUrl $getPlanUrl,
        protected CancelCurrentPlan $cancelCurrentPlan,
    ) {}

    public function billingEnabled(): bool
    {
        return Util::getShopifyConfig('billing_enabled') === true;
    }

    public function freemiumEnabled(): bool
    {
        return Util::getShopifyConfig('billing_freemium_enabled') === true;
    }

    /**
     * Whether the shop may use paid-gated features.
     */
    public function hasActivePaidPlan(ShopModel $shop): bool
    {
        $state = $this->getBillingState($shop);

        return $state['status'] === 'active' && $state['is_paid'] === true;
    }

    /**
     * @return array{
     *     billing_enabled: bool,
     *     freemium_enabled: bool,
     *     status: string,
     *     is_paid: bool,
     *     is_freemium: bool,
     *     is_grandfathered: bool,
     *     current_plan: ?array<string, mixed>,
     *     charge: ?array<string, mixed>,
     *     can_cancel: bool
     * }
     */
    public function getBillingState(ShopModel $shop): array
    {
        $plan = $shop->plan;
        $isFreemium = $shop->isFreemium();
        $isGrandfathered = $shop->isGrandfathered();

        if ($plan === null) {
            $status = ($isFreemium || $isGrandfathered || $this->freemiumEnabled())
                ? 'free'
                : 'none';

            return [
                'billing_enabled' => $this->billingEnabled(),
                'freemium_enabled' => $this->freemiumEnabled(),
                'status' => $status,
                'is_paid' => false,
                'is_freemium' => $isFreemium || ($status === 'free' && $this->freemiumEnabled()),
                'is_grandfathered' => $isGrandfathered,
                'current_plan' => $this->freePlanPayload(),
                'charge' => null,
                'can_cancel' => false,
            ];
        }

        $charge = $this->chargeHelper->chargeForPlan($plan->getId(), $shop);
        $status = $this->resolveChargeStatus($charge);

        return [
            'billing_enabled' => $this->billingEnabled(),
            'freemium_enabled' => $this->freemiumEnabled(),
            'status' => $status,
            'is_paid' => in_array($status, ['active', 'pending'], true),
            'is_freemium' => false,
            'is_grandfathered' => $isGrandfathered,
            'current_plan' => $this->planToArray($plan),
            'charge' => $charge ? $this->chargeToArray($charge) : null,
            'can_cancel' => $status === 'active',
        ];
    }

    /**
     * Plans for the billing UI (Free + paid rows from DB).
     *
     * @return list<array<string, mixed>>
     */
    public function listAvailablePlans(?ShopModel $shop = null): array
    {
        $currentPlanId = $shop?->plan_id;
        $state = $shop ? $this->getBillingState($shop) : null;
        $plans = [];

        $free = $this->freePlanPayload();
        $free['is_current'] = $shop !== null
            && ($currentPlanId === null)
            && in_array($state['status'] ?? null, ['free', 'none'], true);
        $plans[] = $free;

        foreach ($this->planQuery->getAll() as $plan) {
            /** @var Plan $plan */
            $row = $this->planToArray($plan);
            $row['is_current'] = $currentPlanId !== null && (int) $currentPlanId === (int) $plan->id;
            $row['subscribe_allowed'] = (float) $plan->price > 0;
            $plans[] = $row;
        }

        return $plans;
    }

    /**
     * Ensure a paid plan id exists before handing off to package /billing/{plan}.
     */
    public function assertPaidPlanExists(int $planId): Plan
    {
        $this->assertBillingEnabled();

        $plan = $this->planQuery->getById(PlanId::fromNative($planId));
        if ($plan === null || (float) $plan->price <= 0) {
            throw new ShopifyBillingException(
                'Invalid or non-billable plan selected.',
                ShopifyBillingException::CODE_INVALID_PLAN,
                ['plan_id' => $planId],
            );
        }

        return $plan;
    }

    /**
     * Build Shopify confirmation URL for a paid plan (via package GetPlanUrl).
     */
    public function createSubscriptionUrl(ShopModel $shop, int $planId, string $host): string
    {
        $this->assertShopHasToken($shop);
        $this->assertPaidPlanExists($planId);

        try {
            return ($this->getPlanUrl)(
                $shop->getId(),
                NullablePlanId::fromNative($planId),
                $host
            );
        } catch (ShopifyBillingException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Shopify billing create charge failed', [
                'shop' => $shop->getDomain()->toNative(),
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            throw new ShopifyBillingException(
                'Unable to start Shopify billing for this plan.',
                ShopifyBillingException::CODE_SHOPIFY_ERROR,
                ['plan_id' => $planId],
                $e,
            );
        }
    }

    /**
     * Cancel the shop's current plan locally (package CancelCurrentPlan + freemium).
     *
     * Note: the installed package cancels the local charge record; merchants may also
     * need to manage the subscription in Shopify Admin for production apps.
     */
    public function cancelSubscription(ShopModel $shop): bool
    {
        $this->assertBillingEnabled();
        $this->assertShopHasToken($shop);

        if ($shop->plan === null) {
            throw new ShopifyBillingException(
                'No active paid plan to cancel.',
                ShopifyBillingException::CODE_NO_ACTIVE_SUBSCRIPTION,
            );
        }

        try {
            ($this->cancelCurrentPlan)($shop->getId());

            // Drop plan assignment and restore freemium access when enabled.
            $shop->plan_id = null;
            if ($this->freemiumEnabled()) {
                $shop->shopify_freemium = true;
            }
            $shop->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('Shopify billing cancel failed', [
                'shop' => $shop->getDomain()->toNative(),
                'error' => $e->getMessage(),
            ]);

            throw new ShopifyBillingException(
                'Unable to cancel the current subscription.',
                ShopifyBillingException::CODE_SHOPIFY_ERROR,
                [],
                $e,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function freePlanPayload(): array
    {
        $free = config('shopify-plans.free', []);

        return [
            'id' => null,
            'key' => $free['key'] ?? 'free',
            'name' => $free['name'] ?? 'Free',
            'price' => (float) ($free['price'] ?? 0),
            'interval' => null,
            'trial_days' => (int) ($free['trial_days'] ?? 0),
            'description' => $free['description'] ?? null,
            'features' => $free['features'] ?? [],
            'is_paid' => false,
            'subscribe_allowed' => false,
            'is_current' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planToArray(Plan $plan): array
    {
        $configMeta = $this->configMetaForPlanName((string) $plan->name);

        return [
            'id' => (int) $plan->id,
            'key' => $configMeta['key'] ?? strtolower((string) $plan->name),
            'name' => (string) $plan->name,
            'price' => (float) $plan->price,
            'interval' => $plan->interval,
            'trial_days' => (int) ($plan->trial_days ?? 0),
            'test' => (bool) $plan->test,
            'on_install' => (bool) $plan->on_install,
            'description' => $configMeta['description'] ?? null,
            'features' => $configMeta['features'] ?? [],
            'is_paid' => (float) $plan->price > 0,
            'subscribe_allowed' => (float) $plan->price > 0,
            'is_current' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function chargeToArray(object $charge): array
    {
        return [
            'id' => (int) $charge->id,
            'charge_id' => (int) $charge->charge_id,
            'status' => $charge->status,
            'price' => isset($charge->price) ? (float) $charge->price : null,
            'name' => $charge->name ?? null,
            'cancelled_on' => $charge->cancelled_on ?? null,
            'is_active' => method_exists($charge, 'isActive') ? $charge->isActive() : false,
            'is_cancelled' => method_exists($charge, 'isCancelled') ? $charge->isCancelled() : false,
        ];
    }

    protected function resolveChargeStatus(?object $charge): string
    {
        if ($charge === null) {
            return 'active';
        }

        if (method_exists($charge, 'isCancelled') && $charge->isCancelled()) {
            return 'cancelled';
        }

        if (method_exists($charge, 'isDeclined') && $charge->isDeclined()) {
            return 'declined';
        }

        if (method_exists($charge, 'isStatus') && $charge->isStatus(ChargeStatus::PENDING())) {
            return 'pending';
        }

        if (method_exists($charge, 'isActive') && $charge->isActive()) {
            return 'active';
        }

        if (method_exists($charge, 'isAccepted') && $charge->isAccepted()) {
            return 'active';
        }

        return 'active';
    }

    /**
     * @return array<string, mixed>
     */
    protected function configMetaForPlanName(string $name): array
    {
        foreach (config('shopify-plans.paid', []) as $plan) {
            if (strcasecmp((string) ($plan['name'] ?? ''), $name) === 0) {
                return $plan;
            }
        }

        return [];
    }

    protected function assertBillingEnabled(): void
    {
        if (! $this->billingEnabled()) {
            throw new ShopifyBillingException(
                'Shopify billing is disabled.',
                ShopifyBillingException::CODE_BILLING_DISABLED,
            );
        }
    }

    protected function assertShopHasToken(ShopModel $shop): void
    {
        $token = (string) ($shop->password ?? '');
        if ($token === '') {
            throw new ShopifyBillingException(
                'Shop has no offline access token.',
                ShopifyBillingException::CODE_MISSING_TOKEN,
            );
        }
    }
}
