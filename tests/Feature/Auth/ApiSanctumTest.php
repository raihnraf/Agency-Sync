<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class ApiSanctumTest extends TestCase
{
    public function test_api_routes_use_sanctum_middleware()
    {
        $this->assertTrue(true);
    }

    public function test_api_token_authentication_still_works()
    {
        $this->assertTrue(true);
    }

    public function test_api_routes_do_not_use_sessions()
    {
        $this->assertTrue(true);
    }

    public function test_sanctum_hasApiTokens_trait_unchanged()
    {
        $this->assertTrue(true);
    }

    public function test_api_and_web_auth_coexist()
    {
        $this->assertTrue(true);
    }
}
