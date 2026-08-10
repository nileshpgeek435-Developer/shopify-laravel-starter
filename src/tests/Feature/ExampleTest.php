<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_boots(): void
    {
        $this->assertTrue(app()->isBooted());
        $this->assertNotEmpty(config('app.name'));
    }
}
