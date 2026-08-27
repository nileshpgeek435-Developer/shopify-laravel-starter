<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Osiset\ShopifyApp\Objects\Enums\PlanInterval;
use Osiset\ShopifyApp\Objects\Enums\PlanType;
use Osiset\ShopifyApp\Util;

/**
 * Syncs config/shopify-plans.php paid plans into the package `plans` table.
 */
class ShopifyPlanSeeder extends Seeder
{
    public function run(): void
    {
        $table = Util::getShopifyConfig('table_names.plans', 'plans');
        $forceTest = (bool) env('SHOPIFY_BILLING_TEST_CHARGES', true);
        $now = now();

        foreach (config('shopify-plans.paid', []) as $plan) {
            $type = strtolower((string) ($plan['type'] ?? 'recurring')) === 'onetime'
                ? PlanType::ONETIME()->toNative()
                : PlanType::RECURRING()->toNative();

            $intervalName = strtoupper((string) ($plan['interval'] ?? 'EVERY_30_DAYS'));
            $interval = $intervalName === 'ANNUAL'
                ? PlanInterval::ANNUAL()->toNative()
                : PlanInterval::EVERY_30_DAYS()->toNative();

            $attributes = [
                'type' => $type,
                'price' => (float) ($plan['price'] ?? 0),
                'capped_amount' => $plan['capped_amount'] ?? null,
                'terms' => $plan['terms'] ?? null,
                'trial_days' => (int) ($plan['trial_days'] ?? 0),
                'test' => $forceTest ? true : (bool) ($plan['test'] ?? false),
                'on_install' => (bool) ($plan['on_install'] ?? false),
                'interval' => $interval,
                'updated_at' => $now,
            ];

            $existing = DB::table($table)->where('name', $plan['name'])->first();

            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update($attributes);
            } else {
                DB::table($table)->insert($attributes + [
                    'name' => $plan['name'],
                    'created_at' => $now,
                ]);
            }
        }
    }
}
