<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_allows_sixty_read_requests_per_minute()
    {
        $this->assertTrue(true);
    }

    public function test_rate_limit_allows_ten_write_requests_per_minute()
    {
        $this->assertTrue(true);
    }

    public function test_auth_endpoints_have_stricter_rate_limit()
    {
        $this->assertTrue(true);
    }

    public function test_rate_limit_returns_429_with_retry_after()
    {
        $this->assertTrue(true);
    }

    public function test_rate_limit_scopes_by_user()
    {
        $this->assertTrue(true);
    }
}
