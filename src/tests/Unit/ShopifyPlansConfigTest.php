<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyPlansConfigTest extends TestCase
{
    #[Test]
    public function shopify_plans_config_defines_free_basic_and_pro(): void
    {
        $free = config('shopify-plans.free');
        $paid = config('shopify-plans.paid');

        $this->assertSame('Free', $free['name']);
        $this->assertSame(0.0, (float) $free['price']);
        $this->assertArrayHasKey('basic', $paid);
        $this->assertArrayHasKey('pro', $paid);
        $this->assertGreaterThan(0, (float) $paid['basic']['price']);
        $this->assertGreaterThan(0, (float) $paid['pro']['price']);
    }
}
