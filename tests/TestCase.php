<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Route throttles use cache-backed counters that can survive between local
        // PHPUnit processes (especially with the file cache driver). They protect
        // production traffic, but should not make unrelated functional tests flaky.
        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
